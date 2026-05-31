-- ============================================================
-- migration_militantes.sql
-- Estructura del modulo Partido Politico: Militantes y cargos
-- Ejecutar una vez en phpMyAdmin o terminal MySQL.
-- ============================================================

CREATE TABLE IF NOT EXISTS militante_cargos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL UNIQUE,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  orden INT NOT NULL DEFAULT 0,
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO militante_cargos (nombre, orden) VALUES
  ('Coordinador Provincial', 10),
  ('Coordinador Distrital', 20),
  ('Secretario', 30),
  ('Delegado', 40),
  ('Dirigente', 50);

CREATE TABLE IF NOT EXISTS militantes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  simpatizante_id INT NULL,
  nombre VARCHAR(150) NOT NULL,
  dni CHAR(8) NOT NULL UNIQUE,
  celular VARCHAR(20) NULL,
  whatsapp VARCHAR(20) NULL,
  correo VARCHAR(150) NULL,
  cargo_id INT NULL,
  fecha_ingreso DATE NOT NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_militantes_estado (estado),
  INDEX idx_militantes_cargo (cargo_id),
  INDEX idx_militantes_fecha (fecha_ingreso),
  CONSTRAINT fk_militantes_simpatizante
    FOREIGN KEY (simpatizante_id) REFERENCES simpatizantes(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_militantes_cargo
    FOREIGN KEY (cargo_id) REFERENCES militante_cargos(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS militante_mensajes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  canal ENUM('whatsapp','sms','correo') NOT NULL,
  asunto VARCHAR(180) NULL,
  mensaje TEXT NOT NULL,
  alcance ENUM('individual','grupo','masivo') NOT NULL DEFAULT 'individual',
  adjunto_nombre VARCHAR(180) NULL,
  adjunto_ruta VARCHAR(255) NULL,
  adjunto_tipo VARCHAR(120) NULL,
  adjunto_tamanio INT NULL,
  creado_por INT NULL,
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_militante_mensajes_canal (canal),
  INDEX idx_militante_mensajes_creado (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS militante_mensaje_destinatarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mensaje_id INT NOT NULL,
  militante_id INT NOT NULL,
  estado ENUM('pendiente','enviado','fallido') NOT NULL DEFAULT 'pendiente',
  enviado_en DATETIME NULL,
  error TEXT NULL,
  UNIQUE KEY uq_mensaje_militante (mensaje_id, militante_id),
  CONSTRAINT fk_mmd_mensaje
    FOREIGN KEY (mensaje_id) REFERENCES militante_mensajes(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_mmd_militante
    FOREIGN KEY (militante_id) REFERENCES militantes(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS militante_mensaje_canales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mensaje_id INT NOT NULL,
  canal ENUM('whatsapp','sms','correo') NOT NULL,
  UNIQUE KEY uq_mensaje_canal (mensaje_id, canal),
  CONSTRAINT fk_mmc_mensaje
    FOREIGN KEY (mensaje_id) REFERENCES militante_mensajes(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
