<?php
// ============================================================
// maintenance_check.php — Verifica modo mantenimiento
// Incluir DESPUÉS de cargar $cfg_camp (configuracion de la BD)
// Bypass automático si hay sesión admin activa
// ============================================================

if (!isset($cfg_camp)) return; // Requiere $cfg_camp cargado

// Solo aplica si el modo mantenimiento está activo
if (cfg_value($cfg_camp, 'maintenance_active', '0') !== '1') return;

// Bypass: si el admin tiene sesión activa, ve el sitio normal
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['admin_id'])) return;

// Mostrar página de mantenimiento y detener ejecución
require_once __DIR__ . '/../maintenance.php';
exit;
