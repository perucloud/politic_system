-- ============================================================
-- migration_noticias.sql — MySQL 8.x compatible — ivancisneros
-- Ejecutar en phpMyAdmin o CLI
-- ============================================================

USE ivancisneros;

-- 1. Tabla de categorías de noticias
CREATE TABLE IF NOT EXISTS categorias_noticias (
  id     INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  slug   VARCHAR(100) NOT NULL UNIQUE,
  color  VARCHAR(20)  NOT NULL DEFAULT '#1E3A8A'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO categorias_noticias (nombre, slug, color) VALUES
  ('General',    'general',    '#6B7280'),
  ('Campaña',    'campana',    '#1E3A8A'),
  ('Actividad',  'actividad',  '#059669'),
  ('Propuesta',  'propuesta',  '#7C3AED'),
  ('Logros',     'logros',     '#D97706'),
  ('Prensa',     'prensa',     '#DC2626');

-- 2. Ampliar contenido a LONGTEXT
ALTER TABLE noticias MODIFY contenido LONGTEXT;

-- 3. Nuevas columnas (cada una por separado para tolerar si ya existe alguna)
ALTER TABLE noticias ADD COLUMN autor_id        INT          DEFAULT NULL AFTER fecha;
ALTER TABLE noticias ADD COLUMN seo_title       VARCHAR(100) DEFAULT NULL AFTER autor_id;
ALTER TABLE noticias ADD COLUMN seo_description VARCHAR(200) DEFAULT NULL AFTER seo_title;
ALTER TABLE noticias ADD COLUMN seo_keywords    VARCHAR(255) DEFAULT NULL AFTER seo_description;
ALTER TABLE noticias ADD COLUMN slug            VARCHAR(300) DEFAULT NULL AFTER seo_keywords;
