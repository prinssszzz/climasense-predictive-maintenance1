CREATE DATABASE IF NOT EXISTS climasense CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE climasense;
CREATE TABLE roles (id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(40) NOT NULL UNIQUE, name VARCHAR(80) NOT NULL) ENGINE=InnoDB;
INSERT INTO roles (code, name) VALUES ('client_admin', 'Client / Shop Administrator'), ('consumer', 'Consumer / AC Owner');
CREATE TABLE permissions (id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(80) NOT NULL UNIQUE, description VARCHAR(180) NOT NULL) ENGINE=InnoDB;
INSERT INTO permissions (code, description) VALUES ('units.manage','Create and manage shop units'),('units.view_own','View personally owned units'),('maintenance.manage','Schedule and update maintenance'),('alerts.view','View maintenance alerts'),('analytics.view','View predictive analytics');
-- Additional permissions for RBAC
INSERT INTO permissions (code, description) VALUES
	('users.manage','Create and manage users'),
	('invites.create','Generate invite tokens'),
	('invites.view','View invite tokens'),
	('settings.manage','Manage system settings'),
	('audit.view','View audit logs');
CREATE TABLE role_permissions (role_id TINYINT UNSIGNED NOT NULL, permission_id SMALLINT UNSIGNED NOT NULL, PRIMARY KEY(role_id,permission_id), FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE CASCADE, FOREIGN KEY(permission_id) REFERENCES permissions(id) ON DELETE CASCADE) ENGINE=InnoDB;
-- Existing mapping for client_admin and consumer
INSERT INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r JOIN permissions p WHERE r.code='client_admin' OR (r.code='consumer' AND p.code IN ('units.view_own','alerts.view','maintenance.manage','analytics.view'));

-- Seed hierarchical admin roles
INSERT INTO roles (code, name) VALUES ('super_admin', 'Super Administrator'), ('admin_level_1', 'Organization Administrator'), ('admin_level_2', 'Organization Operator');

-- Map permissions to new roles
-- super_admin gets everything
INSERT INTO role_permissions(role_id, permission_id)
	SELECT sr.id, p.id FROM roles sr JOIN permissions p WHERE sr.code = 'super_admin';

-- admin_level_1: org-level management, can create invites for admin_level_2 and consumers
INSERT INTO role_permissions(role_id,permission_id)
	SELECT r.id,p.id FROM roles r JOIN permissions p WHERE r.code='admin_level_1' AND p.code IN ('units.manage','maintenance.manage','alerts.view','analytics.view','users.manage','invites.create','invites.view');

-- admin_level_2: operational (no invite creation)
INSERT INTO role_permissions(role_id,permission_id)
	SELECT r.id,p.id FROM roles r JOIN permissions p WHERE r.code='admin_level_2' AND p.code IN ('units.manage','maintenance.manage','alerts.view');
CREATE TABLE organizations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(160) NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;
CREATE TABLE users (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,organization_id BIGINT UNSIGNED NULL,name VARCHAR(120) NOT NULL,email VARCHAR(190) NOT NULL UNIQUE,password_hash VARCHAR(255) NULL,google_sub VARCHAR(255) NULL UNIQUE,avatar_url VARCHAR(500) NULL,auth_provider ENUM('local','google') NOT NULL DEFAULT 'local',created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE SET NULL) ENGINE=InnoDB;
CREATE TABLE user_roles (user_id BIGINT UNSIGNED NOT NULL,role_id TINYINT UNSIGNED NOT NULL,PRIMARY KEY(user_id,role_id),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE ac_units (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,organization_id BIGINT UNSIGNED NOT NULL,consumer_user_id BIGINT UNSIGNED NULL,unit_code VARCHAR(40) NOT NULL UNIQUE,name VARCHAR(150) NOT NULL,location VARCHAR(180) NOT NULL,model VARCHAR(150) NULL,installed_on DATE NULL,runtime_hours INT UNSIGNED NOT NULL DEFAULT 0,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE,FOREIGN KEY(consumer_user_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB;
CREATE TABLE sensor_readings (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,unit_id BIGINT UNSIGNED NOT NULL,recorded_at DATETIME NOT NULL,temperature_c DECIMAL(5,2) NULL,pressure_psi DECIMAL(7,2) NULL,current_amp DECIMAL(6,2) NULL,vibration_mms DECIMAL(6,3) NULL,humidity_pct DECIMAL(5,2) NULL,INDEX(unit_id,recorded_at),FOREIGN KEY(unit_id) REFERENCES ac_units(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE predictions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,unit_id BIGINT UNSIGNED NOT NULL,predicted_at DATETIME NOT NULL,health_score TINYINT UNSIGNED NOT NULL,status ENUM('healthy','warning','critical') NOT NULL,remaining_useful_life_days INT UNSIGNED NOT NULL,model_version VARCHAR(50) NULL,INDEX(unit_id,predicted_at),FOREIGN KEY(unit_id) REFERENCES ac_units(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE alerts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,unit_id BIGINT UNSIGNED NOT NULL,severity ENUM('info','warning','critical') NOT NULL,alert_type VARCHAR(80) NOT NULL,message TEXT NOT NULL,status ENUM('open','acknowledged','resolved') NOT NULL DEFAULT 'open',created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(unit_id) REFERENCES ac_units(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE maintenance_tasks (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,unit_id BIGINT UNSIGNED NOT NULL,scheduled_for DATE NOT NULL,task VARCHAR(255) NOT NULL,notes TEXT NULL,status ENUM('scheduled','urgent','completed') NOT NULL DEFAULT 'scheduled',created_by BIGINT UNSIGNED NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(unit_id) REFERENCES ac_units(id) ON DELETE CASCADE,FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB;

-- Application maintenance log keyed by the CSV unit codes shown in the fleet UI.
CREATE TABLE maintenance_log_entries (
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	service_date DATE NOT NULL,
	unit_code VARCHAR(40) NOT NULL,
	unit_name VARCHAR(150) NOT NULL,
	task VARCHAR(255) NOT NULL,
	technician VARCHAR(120) NOT NULL DEFAULT 'Unassigned',
	status ENUM('scheduled','urgent','completed') NOT NULL DEFAULT 'scheduled',
	created_by BIGINT UNSIGNED NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	INDEX(service_date), INDEX(unit_code),
	FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Invite tokens for admin onboarding (one-time use)
CREATE TABLE invites (
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	token_hash CHAR(64) NOT NULL,
	role_code VARCHAR(40) NOT NULL DEFAULT 'client_admin',
	organization_id BIGINT UNSIGNED NULL,
	created_by BIGINT UNSIGNED NULL,
	expires_at DATETIME NULL,
	used_by BIGINT UNSIGNED NULL,
	used_at DATETIME NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
	FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
	FOREIGN KEY(used_by) REFERENCES users(id) ON DELETE SET NULL,
	UNIQUE(token_hash)
) ENGINE=InnoDB;
