-- ============================================================
-- migration_paginas_menu.sql
-- Ejecutar en phpMyAdmin (base de datos: ivancisneros)
-- ============================================================

USE ivancisneros;

-- 1. Tabla de páginas personalizadas
CREATE TABLE IF NOT EXISTS paginas (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  titulo          VARCHAR(300)  NOT NULL,
  slug            VARCHAR(300)  NOT NULL UNIQUE,
  contenido       LONGTEXT,
  seo_title       VARCHAR(100)  DEFAULT NULL,
  seo_description VARCHAR(200)  DEFAULT NULL,
  seo_keywords    VARCHAR(255)  DEFAULT NULL,
  estado          ENUM('publicado','borrador') NOT NULL DEFAULT 'borrador',
  autor_id        INT           DEFAULT NULL,
  creado_en       DATETIME      DEFAULT CURRENT_TIMESTAMP,
  actualizado     DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla de ítems del menú principal
CREATE TABLE IF NOT EXISTS menu_items (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  label   VARCHAR(100)  NOT NULL,
  url     VARCHAR(500)  NOT NULL DEFAULT '/',
  tipo    ENUM('interno','pagina','noticia','externo','auto-candidatos') NOT NULL DEFAULT 'interno',
  target  ENUM('_self','_blank') NOT NULL DEFAULT '_self',
  orden   INT       NOT NULL DEFAULT 0,
  visible TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Insertar menú por defecto (igual al navbar actual)
INSERT IGNORE INTO menu_items (id, label, url, tipo, target, orden, visible) VALUES
  (1, 'Inicio',                '/index.php',          'interno',         '_self', 10, 1),
  (2, '¿Quién es?',            '/index.php#quien-es', 'interno',         '_self', 20, 1),
  (3, 'Plan de Gobierno',      '/plan.php',           'interno',         '_self', 30, 1),
  (4, 'Candidatos Distritales','#',                   'auto-candidatos', '_self', 40, 1),
  (5, 'Noticias',              '/noticias/index.php', 'interno',         '_self', 50, 1),
  (6, 'Únete',                 '/index.php#unete',    'interno',         '_self', 60, 1);
