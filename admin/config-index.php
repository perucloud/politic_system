<?php
// ============================================================
// config-index.php - Configuracion editable de la pagina principal
// Fase 1-5: Configuracion editable de portada
// ============================================================
if (ob_get_level() === 0) ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/../includes/helpers/config.php';

require_login();
require_rol('admin');
require_modulo('configuracion_global', $pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

$config = [];
try {
    $config = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $config = [];
}

// Estadisticas de visitas para tab Contador
$visit_stats = ['total' => 0, 'hoy' => 0, 'semana' => 0, 'mes' => 0];
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS visitas (
        id        INT AUTO_INCREMENT PRIMARY KEY,
        ip_hash   VARCHAR(64) NOT NULL,
        fecha     DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_fecha (ip_hash, fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $row = $pdo->query("SELECT
        COUNT(*) AS total,
        SUM(fecha = CURDATE()) AS hoy,
        SUM(fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) AS semana,
        SUM(fecha >= DATE_FORMAT(CURDATE(),'%Y-%m-01')) AS mes
    FROM visitas")->fetch(PDO::FETCH_ASSOC);
    $visit_stats = [
        'total'  => (int)($row['total']  ?? 0),
        'hoy'    => (int)($row['hoy']    ?? 0),
        'semana' => (int)($row['semana'] ?? 0),
        'mes'    => (int)($row['mes']    ?? 0),
    ];
} catch (Exception $e) {}

$hero_defaults = [
    'index_hero_badge_letter' => 'R',
    'index_hero_badge_label' => 'ALIANZA PARA EL PROGRESO',
    'index_hero_title_line1' => 'LIC.',
    'index_hero_title_line2' => 'IVAN',
    'index_hero_title_line3' => 'CISNEROS',
    'index_hero_kicker' => 'Candidato - Alcalde Provincial',
    'index_hero_location' => 'Satipo, Junin - 2026 - 2030',
    'index_hero_quote' => '"Ha llegado el momento de transformar Satipo"',
    'index_hero_primary_text' => 'Conoce el Plan',
    'index_hero_primary_url' => '/plan.php',
    'index_hero_secondary_text' => 'Unete al Equipo',
    'index_hero_secondary_url' => '#unete',
    'index_hero_img' => '/assets/img/candidato/ivancisneros.webp',
    'index_hero_profile_img' => '/assets/img/candidato/ivancisneros-perfil.webp',
    'index_hero_fallback_img' => '/assets/img/candidato/ivancisneros.webp',
    'index_hero_party_img' => '/assets/img/candidato/r.webp',
    'index_hero_float_title' => 'ALCALDE POR SATIPO',
    'index_hero_float_subtitle' => 'Gestion 2026 - 2030',
];

$hero_stats_default = [
    ['n' => '8', 'l' => 'Distritos'],
    ['n' => '4', 'l' => 'Anos de gestion'],
    ['n' => '8', 'l' => 'Ejes de trabajo'],
];

$hero2_defaults = [
    'index_hero2_bg_img' => 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=1600&q=80',
    'index_hero2_title_line1' => 'Satipo merece',
    'index_hero2_title_highlight' => 'orden, desarrollo',
    'index_hero2_title_line3' => 'y oportunidades reales',
    'index_hero2_text' => 'Nuestra provincia amazonica tiene todo el potencial para crecer. Con liderazgo honesto, planificacion real y trabajo en equipo, juntos construiremos el Satipo que merecemos.',
    'index_hero2_button_text'    => 'Ver el Plan de Gobierno',
    'index_hero2_button_url'     => '/plan.php',
    'index_hero2_onpe_text'      => 'Conoce tu local de votacion',
    'index_hero2_onpe_url'       => 'https://consultaelectoral.onpe.gob.pe/inicio',
    'index_hero2_onpe_subtitle'  => 'Ademas verifica si eres miembro de mesa',
];

$hero2_pillars_default = [
    ['icon' => 'obra', 'title' => 'Infraestructura', 'desc' => 'Vias, puentes y obras reales'],
    ['icon' => 'gestion', 'title' => 'Gestion honesta', 'desc' => 'Transparencia total'],
    ['icon' => 'campo', 'title' => 'Desarrollo rural', 'desc' => 'Campo y comunidades primero'],
];

$bio_defaults = [
    'index_bio_img' => '/uploads/media/media_6a1634dc12ada8.95137384.webp',
    'index_bio_fallback_img' => '/uploads/media/media_6a1634dc12ada8.95137384.webp',
    'index_bio_badge_number' => '+10',
    'index_bio_badge_label' => 'anos de trayectoria',
    'index_bio_eyebrow' => 'Conocelo',
    'index_bio_title' => 'Quien es|Ivan Cisneros?',
    'index_bio_p1' => 'El Lic. Ivan Cisneros es un profesional satipeno comprometido con el desarrollo de su provincia. Con formacion en ingenieria y amplia trayectoria en gestion publica, conoce de primera mano las necesidades de cada comunidad de Satipo.',
    'index_bio_p2' => 'Nacido y criado en la region, ha dedicado su carrera al servicio de la gente, impulsando proyectos de infraestructura, agricultura y desarrollo comunitario en los ocho distritos de la provincia.',
    'index_bio_button_text' => 'Ver biografia completa',
    'index_bio_button_url' => '#biografia',
];

$bio_attrs_default = [
    ['icon' => 'compromiso', 'title' => 'Compromiso', 'desc' => 'Con la gente y la provincia'],
    ['icon' => 'experiencia', 'title' => 'Experiencia', 'desc' => 'En gestion y proyectos'],
    ['icon' => 'cercania', 'title' => 'Cercania', 'desc' => 'Con todas las comunidades'],
];

$work_axes_default = cfg_default_work_axes();

$work_section_defaults = [
    'index_work_eyebrow' => 'Propuestas 2026',
    'index_work_title' => 'Nuestros Ejes de Trabajo',
    'index_work_text' => 'Un plan concreto, realista y comprometido para transformar Satipo en cuatro anos.',
    'index_work_button_text' => 'Ver Plan Completo 2026-2030',
    'index_work_button_url' => '/plan.php',
];

$blocks_defaults = [
    'index_news_eyebrow' => 'Actualidad',
    'index_news_title' => 'Ultimas Noticias',
    'index_news_text' => 'Enterate de todo lo que pasa en la campana.',
    'index_news_button_text' => 'Ver todas las noticias',
    'index_news_button_url' => '/noticias/index.php',
    'index_news_empty_text' => 'Proximamente publicaremos las ultimas noticias de la campana.',
    'index_join_title' => 'Se parte del cambio|en Satipo',
    'index_join_text' => 'Suma tu energia, tus ideas y tu compromiso a este equipo.',
    'index_join_name_label' => 'Nombre completo *',
    'index_join_name_placeholder' => 'Tu nombre completo',
    'index_join_dni_label' => 'DNI *',
    'index_join_phone_label' => 'Telefono *',
    'index_join_district_label' => 'Distrito *',
    'index_join_district_placeholder' => 'Selecciona tu distrito',
    'index_join_button_text' => 'Me uno al equipo de Ivan Cisneros',
    'index_agenda_eyebrow' => 'Calendario',
    'index_agenda_title' => 'Proximas Actividades',
    'index_agenda_text' => 'Conoce el itinerario de campana de Ivan Cisneros',
    'index_agenda_empty_text' => 'Proximamente anunciaremos las actividades de campana.',
];

$social_defaults = [
    'index_social_enabled' => '1',
    'index_social_eyebrow' => 'Siguenos en nuestras redes sociales',
    'index_social_title' => 'Unete y se parte del cambio',
    'index_social_subtitle' => 'Siguenos en nuestra cuenta oficial de Facebook y enterate de todas nuestras actividades partidarias, juntos por un Satipo progresista.',
    'index_social_image' => '/assets/img/candidato/ivancisneros.webp',
    'index_social_image_alt' => 'Ivan Cisneros por Satipo',
    'index_social_button_text' => 'Unete a nosotros..!',
    'index_social_button_url' => 'https://www.facebook.com/ivanrogercisnerosquispe',
    'index_social_facebook_url' => 'https://www.facebook.com/ivanrogercisnerosquispe',
    'index_social_plugin_height' => '500',
];

$contact_defaults = [
    'index_contact_eyebrow' => 'Encuentranos',
    'index_contact_title' => 'Nuestro Local|de Campana',
    'index_contact_address_label' => 'Direccion',
    'index_contact_address_line1' => 'Jr. Micaela Bastidas 636',
    'index_contact_address_line2' => 'Satipo, Junin - Peru',
    'index_contact_hours_label' => 'Horario de atencion',
    'index_contact_hours_line1' => 'Lunes a Sabado',
    'index_contact_hours_line2' => '8:00 am - 7:00 pm',
    'index_contact_phone_label' => 'Telefono / WhatsApp',
    'index_contact_phone_text' => '932 512 948',
    'index_contact_phone_href' => 'tel:932512948',
    'index_contact_button_text' => 'Como llegar',
    'index_contact_button_url' => 'https://www.google.com/maps/search/Jr.+Micaela+Bastidas+636,+Satipo,+Peru',
    'index_contact_map_iframe' => 'https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d978.2554392895146!2d-74.64025500233248!3d-11.259809609805213!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e0!3m2!1ses!2spe!4v1779183850860!5m2!1ses!2spe',
    'index_contact_map_title' => 'Local de Campana - Jr. Micaela Bastidas 636, Satipo',
];

$hf_defaults = [
    'site_header_logo' => '/assets/img/logos/logorp.webp',
    'site_header_logo_alt' => 'ALIANZA PARA EL PROGRESO',
    'site_header_signature' => 'Ivan Cisneros',
    'site_header_cta_text' => 'Unete al equipo',
    'site_header_cta_url' => '/index.php#unete',
    'site_footer_logo' => '/assets/img/logos/logorp.webp',
    'site_footer_name' => 'Lic. Ivan Cisneros',
    'site_footer_party' => 'ALIANZA PARA EL PROGRESO',
    'site_footer_slogan' => '"Ha llegado el momento de transformar Satipo"',
    'site_footer_nav_title' => 'Navegacion',
    'site_footer_team_title' => 'Candidatos Distritales',
    'site_footer_contact_title' => 'Contacto',
    'site_footer_address' => 'Satipo, Junin, Peru',
    'site_footer_email' => 'contacto@ivancisneros.pe',
    'site_footer_whatsapp_text' => 'WhatsApp',
    'site_footer_whatsapp_number' => '51999999999',
    'site_footer_whatsapp_message' => 'Hola, quiero mas informacion.',
    'site_footer_whatsapp_url' => 'https://wa.me/51999999999',
    'site_footer_facebook_url' => '#',
    'site_footer_instagram_url' => '#',
    'site_footer_tiktok_url' => '#',
    'site_footer_youtube_url' => '#',
    'site_footer_copyright' => 'Todos los derechos reservados.',
    'site_footer_bottom_left' => 'Lic. Ivan Cisneros - Satipo, Junin, Peru',
    'site_footer_bottom_right' => 'Diseno web politico - Todos los derechos reservados',
    'dist_cta_label'  => 'es parte del equipo de',
    'dist_cta_name'   => 'Ivan Cisneros',
    'dist_cta_text'   => 'Juntos trabajamos por el desarrollo integral de toda la provincia de Satipo. Un equipo unido, un solo objetivo.',
    'dist_cta_button' => 'Plan Provincial →',
    'dist_cta_url'    => '/plan.php',
    'dist_cta_photo'  => '/assets/img/candidato/ivancisneros-perfil.webp',
];

function cfg_parse_stats(array $nums, array $labels, array $fallback): array {
    $stats = [];
    for ($i = 0; $i < 3; $i++) {
        $n = trim($nums[$i] ?? '');
        $l = trim($labels[$i] ?? '');
        if ($n !== '' || $l !== '') {
            $stats[] = ['n' => $n, 'l' => $l];
        }
    }
    return $stats ?: $fallback;
}

function cfg_parse_pillars(array $icons, array $titles, array $descs, array $fallback): array {
    $pillars = [];
    for ($i = 0; $i < 3; $i++) {
        $icon = trim($icons[$i] ?? '');
        $title = trim($titles[$i] ?? '');
        $desc = trim($descs[$i] ?? '');
        if ($icon !== '' || $title !== '' || $desc !== '') {
            $pillars[] = ['icon' => $icon, 'title' => $title, 'desc' => $desc];
        }
    }
    return $pillars ?: $fallback;
}

function cfg_parse_cards(array $icons, array $titles, array $descs, array $fallback): array {
    return cfg_parse_pillars($icons, $titles, $descs, $fallback);
}

function cfg_slug(string $text, string $fallback): string {
    $text = strtolower(trim($text));
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string)$text, '-');
    return $text !== '' ? $text : $fallback;
}

function cfg_parse_axes(array $post, array $fallback): array {
    $titles = $post['eje_title'] ?? [];
    $axes = [];
    $used_ids = [];
    $total = min(count($titles), 20);
    for ($i = 0; $i < $total; $i++) {
        $title = trim($titles[$i] ?? '');
        if ($title === '') continue;
        $id = cfg_slug((string)($post['eje_id'][$i] ?? $title), 'eje-' . ($i + 1));
        $base_id = $id;
        $suffix = 2;
        while (isset($used_ids[$id])) {
            $id = $base_id . '-' . $suffix;
            $suffix++;
        }
        $used_ids[$id] = true;
        $axes[] = [
            'id' => $id,
            'icon' => trim($post['eje_icon'][$i] ?? '') ?: cfg_axis_auto_icon($title . ' ' . (string)($post['eje_label'][$i] ?? '')),
            'label' => trim($post['eje_label'][$i] ?? $title),
            'title' => $title,
            'desc' => trim($post['eje_desc'][$i] ?? ''),
            'grad' => trim($post['eje_grad'][$i] ?? 'from-blue-500 to-indigo-600'),
            'nav_color' => trim($post['eje_nav_color'][$i] ?? 'bg-blue-100 text-blue-700'),
            'nav_border' => trim($post['eje_nav_border'][$i] ?? 'border-blue-300'),
            'section_bg' => trim($post['eje_section_bg'][$i] ?? '#EFF6FF'),
            'section_border' => trim($post['eje_section_border'][$i] ?? '#BFDBFE'),
            'img' => trim($post['eje_img'][$i] ?? ''),
            'plan_desc' => trim($post['eje_plan_desc'][$i] ?? ''),
            'proposals' => trim($post['eje_proposals'][$i] ?? ''),
        ];
    }
    return $axes ?: $fallback;
}

$flash = null;
$flash_type = 'success';
$active_tab = 'hero';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $active_tab = $_POST['tab'] ?? 'hero';
    if ($active_tab === 'hero') {
        $values = [];
        foreach (array_keys($hero_defaults) as $key) {
            $values[$key] = trim($_POST[$key] ?? $hero_defaults[$key]);
        }

        $values['index_hero_stats'] = cfg_parse_stats($_POST['stat_n'] ?? [], $_POST['stat_l'] ?? [], $hero_stats_default);

        $fx_raw = trim($_POST['hero_candidate_effect'] ?? 'none');
        $values['hero_candidate_effect'] = in_array($fx_raw, ['none','halo','sparkles','sweep','arcos','combo','arcos_sparkles'], true) ? $fx_raw : 'none';

        try {
            cfg_save_values($pdo, $values);
            $config = array_merge($config, array_map(
                fn($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string)$v,
                $values
            ));
            log_activity($pdo, 'Actualizo configuracion del Index: Hero', 'configuracion_global');
            $flash = 'Hero actualizado correctamente.';
        } catch (Exception $e) {
            $flash = 'Error al guardar: ' . $e->getMessage();
            $flash_type = 'error';
        }
    }

    if ($active_tab === 'badge') {
        $values = [
            'index_hero_badge_letter' => trim($_POST['index_hero_badge_letter'] ?? $hero_defaults['index_hero_badge_letter']),
            'index_hero_badge_label' => trim($_POST['index_hero_badge_label'] ?? $hero_defaults['index_hero_badge_label']),
            'index_hero_float_title' => trim($_POST['index_hero_float_title'] ?? $hero_defaults['index_hero_float_title']),
            'index_hero_float_subtitle' => trim($_POST['index_hero_float_subtitle'] ?? $hero_defaults['index_hero_float_subtitle']),
            'index_hero_stats' => cfg_parse_stats($_POST['stat_n'] ?? [], $_POST['stat_l'] ?? [], $hero_stats_default),
        ];

        try {
            cfg_save_values($pdo, $values);
            $config = array_merge($config, array_map(
                fn($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string)$v,
                $values
            ));
            log_activity($pdo, 'Actualizo configuracion del Index: Badges', 'configuracion_global');
            $flash = 'Badges actualizados correctamente.';
        } catch (Exception $e) {
            $flash = 'Error al guardar: ' . $e->getMessage();
            $flash_type = 'error';
        }
    }

    if ($active_tab === 'hero2') {
        $values = [];
        foreach (array_keys($hero2_defaults) as $key) {
            $values[$key] = trim($_POST[$key] ?? $hero2_defaults[$key]);
        }
        $values['index_hero2_pillars'] = cfg_parse_pillars(
            $_POST['pillar_icon'] ?? [],
            $_POST['pillar_title'] ?? [],
            $_POST['pillar_desc'] ?? [],
            $hero2_pillars_default
        );

        try {
            cfg_save_values($pdo, $values);
            $config = array_merge($config, array_map(
                fn($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string)$v,
                $values
            ));
            log_activity($pdo, 'Actualizo configuracion del Index: Seccion II', 'configuracion_global');
            $flash = 'Seccion II actualizada correctamente.';
        } catch (Exception $e) {
            $flash = 'Error al guardar: ' . $e->getMessage();
            $flash_type = 'error';
        }
    }

    if ($active_tab === 'bio') {
        $values = [];
        foreach (array_keys($bio_defaults) as $key) {
            $values[$key] = trim($_POST[$key] ?? $bio_defaults[$key]);
        }
        $values['index_bio_attrs'] = cfg_parse_cards(
            $_POST['bio_attr_icon'] ?? [],
            $_POST['bio_attr_title'] ?? [],
            $_POST['bio_attr_desc'] ?? [],
            $bio_attrs_default
        );

        try {
            cfg_save_values($pdo, $values);
            $config = array_merge($config, array_map(
                fn($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string)$v,
                $values
            ));
            log_activity($pdo, 'Actualizo configuracion del Index: Biografia corta', 'configuracion_global');
            $flash = 'Biografia corta actualizada correctamente.';
        } catch (Exception $e) {
            $flash = 'Error al guardar: ' . $e->getMessage();
            $flash_type = 'error';
        }
    }

    if ($active_tab === 'bloques') {
        $values = [];
        foreach (array_keys($blocks_defaults) as $key) {
            $values[$key] = trim($_POST[$key] ?? $blocks_defaults[$key]);
        }

        try {
            cfg_save_values($pdo, $values);
            $config = array_merge($config, array_map('strval', $values));
            log_activity($pdo, 'Actualizo configuracion del Index: Noticias, Unete y Agenda', 'configuracion_global');
            $flash = 'Bloques de noticias, unete y agenda actualizados correctamente.';
        } catch (Exception $e) {
            $flash = 'Error al guardar: ' . $e->getMessage();
            $flash_type = 'error';
        }
    }

    if ($active_tab === 'social') {
        $values = [];
        foreach (array_keys($social_defaults) as $key) {
            $values[$key] = trim($_POST[$key] ?? $social_defaults[$key]);
        }
        $values['index_social_enabled'] = isset($_POST['index_social_enabled']) ? '1' : '0';

        try {
            cfg_save_values($pdo, $values);
            $config = array_merge($config, array_map('strval', $values));
            log_activity($pdo, 'Actualizo configuracion del Index: Redes Sociales', 'configuracion_global');
            $flash = 'Redes sociales actualizadas correctamente.';
        } catch (Exception $e) {
            $flash = 'Error al guardar: ' . $e->getMessage();
            $flash_type = 'error';
        }
    }

    if ($active_tab === 'contacto') {
        $values = [];
        foreach (array_keys($contact_defaults) as $key) {
            $values[$key] = trim($_POST[$key] ?? $contact_defaults[$key]);
        }

        try {
            cfg_save_values($pdo, $values);
            $config = array_merge($config, array_map('strval', $values));
            log_activity($pdo, 'Actualizo configuracion del Index: Contactenos y mapa', 'configuracion_global');
            $flash = 'Contactenos y mapa actualizados correctamente.';
        } catch (Exception $e) {
            $flash = 'Error al guardar: ' . $e->getMessage();
            $flash_type = 'error';
        }
    }

    if ($active_tab === 'header_footer') {
        $values = [];
        foreach (array_keys($hf_defaults) as $key) {
            $values[$key] = trim($_POST[$key] ?? $hf_defaults[$key]);
        }

        try {
            cfg_save_values($pdo, $values);
            $config = array_merge($config, array_map('strval', $values));
            log_activity($pdo, 'Actualizo configuracion del sitio: Header, PreFooter y Footer', 'configuracion_global');
            $flash = 'Header, PreFooter y Footer actualizados correctamente.';
        } catch (Exception $e) {
            $flash = 'Error al guardar: ' . $e->getMessage();
            $flash_type = 'error';
        }
    }
}

function cfg_admin_val(array $config, array $defaults, string $key): string {
    return htmlspecialchars(cfg_value($config, $key, $defaults[$key] ?? ''), ENT_QUOTES);
}

$hero_stats = cfg_json($config, 'index_hero_stats', $hero_stats_default);
for ($i = count($hero_stats); $i < 3; $i++) {
    $hero_stats[] = ['n' => '', 'l' => ''];
}

$hero2_pillars = cfg_json($config, 'index_hero2_pillars', $hero2_pillars_default);
for ($i = count($hero2_pillars); $i < 3; $i++) {
    $hero2_pillars[] = ['icon' => '', 'title' => '', 'desc' => ''];
}

$bio_attrs = cfg_json($config, 'index_bio_attrs', $bio_attrs_default);
for ($i = count($bio_attrs); $i < 3; $i++) {
    $bio_attrs[] = ['icon' => '', 'title' => '', 'desc' => ''];
}

$work_axes = cfg_json($config, 'index_work_axes', $work_axes_default);
$work_axes_json = json_encode($work_axes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

$page_title = 'Configurar Index';
include __DIR__ . '/layout.php';
?>

<style>
  .config-tabs {
    display: flex;
    align-items: flex-end;
    gap: 1px;
    padding: 0 0 0 1px;
    margin-bottom: 1.25rem;
    overflow-x: auto;
    border-bottom: 1px solid #bfc4cf;
    scrollbar-width: thin;
  }

  .config-tab {
    position: relative;
    flex: 0 0 auto;
    width: max-content;
    max-width: 190px;
    min-width: 72px;
    padding: 0.58rem 1rem 0.54rem;
    background: #E0DFDF;
    color: #2f3441;
    border: 1px solid #bfc4cf;
    border-bottom: 0;
    border-radius: 14px 14px 0 0;
    box-shadow: inset -1px 0 0 rgba(255,255,255,.55), 1px 0 4px rgba(15,32,87,.12);
    font-size: .78rem;
    font-weight: 700;
    line-height: 1.05;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: background-color .18s ease, color .18s ease, box-shadow .18s ease, transform .18s ease;
  }

  .config-tab:hover {
    background: #2563EB;
    color: #fff;
    box-shadow: 0 6px 14px rgba(37,99,235,.2);
    transform: translateY(-1px);
  }

  .config-tab.is-active {
    z-index: 2;
    background: #0F2057;
    color: #fff;
    border-color: #0F2057;
    box-shadow: 0 8px 18px rgba(15,32,87,.22);
  }

  .config-tab.is-active:hover {
    background: #0F2057;
  }

  .config-card {
    border: 1px solid #e5e7eb;
    border-radius: 1rem;
    background: #fff;
    box-shadow: 0 10px 28px rgba(15, 32, 87, .06);
    overflow: hidden;
  }

  .config-card-head {
    background: #1E3A8A;
    color: #fff;
    padding: 1rem 1.25rem;
  }

  .config-card-head h2,
  .config-card-head h3 {
    color: #fff;
    font-size: .88rem;
    font-weight: 800;
    margin: 0;
  }

  .config-group {
    border: 1px solid #dbeafe;
    background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
    border-radius: 1rem;
    padding: 1rem;
  }

  .config-group + .config-group {
    margin-top: 1rem;
  }

  .config-group-title {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .75rem;
    margin-bottom: .85rem;
    padding-bottom: .7rem;
    border-bottom: 1px solid #e0ecff;
  }

  .config-group-title h3 {
    margin: 0;
    color: #0F2057;
    font-size: .82rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .035em;
  }

  .config-group-title p {
    margin: .18rem 0 0;
    color: #64748b;
    font-size: .75rem;
    line-height: 1.35;
  }

  .config-field-grid {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: .85rem;
  }

  .config-tabs ~ form .bg-white.rounded-2xl.shadow-sm.border.border-gray-100.overflow-hidden,
  .config-tabs ~ form .bg-white.rounded-2xl.shadow-sm.border.border-gray-100.p-6 {
    border-color: #dbeafe !important;
    box-shadow: 0 12px 30px rgba(15, 32, 87, .06);
  }

  .config-tabs ~ form .bg-white.rounded-2xl.shadow-sm.border.border-gray-100.overflow-hidden > .p-6 {
    background: #fff;
  }

  .config-tabs ~ form .bg-white.rounded-2xl.shadow-sm.border.border-gray-100.overflow-hidden > .p-6 > div:not(.config-group):not(.bg-gray-50):not(.rounded-2xl):not([class*="sticky"]),
  .config-tabs ~ form .bg-white.rounded-2xl.shadow-sm.border.border-gray-100.p-6 > div:not(.config-group):not(.bg-gray-50):not(.rounded-2xl):not([class*="sticky"]) {
    border: 1px solid #e0ecff;
    background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
    border-radius: .95rem;
    padding: .9rem;
  }

  .config-tabs ~ form .bg-white.rounded-2xl.shadow-sm.border.border-gray-100.overflow-hidden > .p-6 > div:not(.config-group):not(.bg-gray-50):not(.rounded-2xl):not([class*="sticky"]) + div:not(.config-group):not(.bg-gray-50):not(.rounded-2xl):not([class*="sticky"]) {
    margin-top: .15rem;
  }

  .config-tabs ~ form .bg-white.rounded-2xl.shadow-sm.border.border-gray-100.overflow-hidden > .p-6.grid > div,
  .config-tabs ~ form .bg-white.rounded-2xl.shadow-sm.border.border-gray-100.overflow-hidden > .p-6 .grid > div {
    min-width: 0;
  }

  @media (min-width: 640px) {
    .config-field-grid.two {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 640px) {
    .config-tab {
      max-width: 150px;
      min-width: 64px;
      padding: 0.52rem .82rem 0.5rem;
      font-size: .74rem;
    }
  }
</style>

<div class="max-w-6xl mx-auto" x-data="{ activeTab: '<?= htmlspecialchars($active_tab) ?>' }">
  <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
      <h1 class="text-2xl font-black text-gray-800">Configurar Index</h1>
      <p class="text-sm text-gray-500 mt-1">Hero principal, bloques de contenido y secciones de la portada.</p>
    </div>
    <a href="<?= BASE_URL ?>/index.php" target="_blank"
       class="inline-flex items-center gap-2 bg-white border border-gray-200 text-gray-600 hover:text-[#1E3A8A] font-bold px-4 py-2.5 rounded-xl text-sm shadow-sm">
      Ver portada
    </a>
  </div>

  <?php if ($flash): ?>
  <div class="mb-5 rounded-xl px-5 py-3.5 text-sm font-semibold border <?= $flash_type === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' ?>">
    <?= htmlspecialchars($flash) ?>
  </div>
  <?php endif; ?>

  <div class="config-tabs">
    <?php
    $tabs = [
        'hero'         => 'Hero',
        'badge'        => 'Badges',
        'hero2'        => 'Seccion II',
        'bio'          => 'Biografia corta',
        'social'       => 'Redes Sociales',
        'bloques'      => 'Noticias / Unete / Agenda',
        'contacto'     => 'Contactenos / Mapa',
        'header_footer'=> 'Header / Footer',
    ];
    foreach ($tabs as $id => $label):
      $enabled = in_array($id, ['hero', 'badge', 'hero2', 'bio', 'social', 'bloques', 'contacto', 'header_footer'], true);
    ?>
    <button type="button"
            @click="<?= $enabled ? "activeTab='{$id}'" : '' ?>"
            class="config-tab <?= $enabled ? '' : 'opacity-50 cursor-not-allowed' ?>"
            :class="activeTab === '<?= $id ?>' ? 'is-active' : ''">
      <?= htmlspecialchars($label) ?>
    </button>
    <?php endforeach; ?>
  </div>

  <form method="POST" x-show="activeTab === 'hero'" class="space-y-6">
    <input type="hidden" name="tab" value="hero">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="bg-[#1E3A8A] px-6 py-4">
            <h2 class="text-white font-bold text-sm">Textos principales</h2>
          </div>
          <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Badge letra</label>
              <input name="index_hero_badge_letter" value="<?= cfg_admin_val($config, $hero_defaults, 'index_hero_badge_letter') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Badge texto</label>
              <input name="index_hero_badge_label" value="<?= cfg_admin_val($config, $hero_defaults, 'index_hero_badge_label') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Titulo linea 1</label>
              <input name="index_hero_title_line1" value="<?= cfg_admin_val($config, $hero_defaults, 'index_hero_title_line1') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Titulo linea 2</label>
              <input name="index_hero_title_line2" value="<?= cfg_admin_val($config, $hero_defaults, 'index_hero_title_line2') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Titulo linea 3</label>
              <input name="index_hero_title_line3" value="<?= cfg_admin_val($config, $hero_defaults, 'index_hero_title_line3') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Kicker / cargo</label>
              <input name="index_hero_kicker" value="<?= cfg_admin_val($config, $hero_defaults, 'index_hero_kicker') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Lugar / periodo</label>
              <input name="index_hero_location" value="<?= cfg_admin_val($config, $hero_defaults, 'index_hero_location') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Frase destacada</label>
              <input name="index_hero_quote" value="<?= cfg_admin_val($config, $hero_defaults, 'index_hero_quote') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="bg-[#1E3A8A] px-6 py-4">
            <h2 class="text-white font-bold text-sm">Botones y estadisticas</h2>
          </div>
          <div class="p-6 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Boton primario</label>
                <input name="index_hero_primary_text" value="<?= cfg_admin_val($config, $hero_defaults, 'index_hero_primary_text') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm mb-2">
                <input name="index_hero_primary_url" value="<?= cfg_admin_val($config, $hero_defaults, 'index_hero_primary_url') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono">
              </div>
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Boton secundario</label>
                <input name="index_hero_secondary_text" value="<?= cfg_admin_val($config, $hero_defaults, 'index_hero_secondary_text') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm mb-2">
                <input name="index_hero_secondary_url" value="<?= cfg_admin_val($config, $hero_defaults, 'index_hero_secondary_url') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono">
              </div>
            </div>

            <!-- Botón ONPE -->
            <div class="border-t border-gray-100 pt-4">
              <label class="block text-xs font-black text-gray-500 uppercase mb-3">
                Boton Consulta Electoral (ONPE) — "Conoce tu local de votacion"
              </label>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                  <label class="block text-[10px] text-gray-400 uppercase mb-1">Texto del boton</label>
                  <input name="index_hero2_onpe_text" value="<?= cfg_admin_val($config, $hero2_defaults, 'index_hero2_onpe_text') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
                </div>
                <div>
                  <label class="block text-[10px] text-gray-400 uppercase mb-1">URL (abre en nueva pestana)</label>
                  <input name="index_hero2_onpe_url" value="<?= cfg_admin_val($config, $hero2_defaults, 'index_hero2_onpe_url') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono">
                </div>
                <div>
                  <label class="block text-[10px] text-gray-400 uppercase mb-1">Subtexto debajo del boton</label>
                  <input name="index_hero2_onpe_subtitle" value="<?= cfg_admin_val($config, $hero2_defaults, 'index_hero2_onpe_subtitle') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
                </div>
              </div>
              <p class="text-[10px] text-gray-400 mt-1.5">Deja la URL vacía para ocultar el botón.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <?php for ($i = 0; $i < 3; $i++): ?>
              <div class="bg-gray-50 rounded-xl border border-gray-100 p-4">
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Stat <?= $i + 1 ?></label>
                <input name="stat_n[]" value="<?= htmlspecialchars($hero_stats[$i]['n'] ?? '', ENT_QUOTES) ?>" placeholder="Numero" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm mb-2">
                <input name="stat_l[]" value="<?= htmlspecialchars($hero_stats[$i]['l'] ?? '', ENT_QUOTES) ?>" placeholder="Etiqueta" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm">
              </div>
              <?php endfor; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="bg-[#1E3A8A] px-6 py-4">
            <h2 class="text-white font-bold text-sm">Imagenes</h2>
          </div>
          <div class="p-6 space-y-4">
            <?php
            $image_fields = [
                'index_hero_img' => 'Imagen principal',
                'index_hero_profile_img' => 'Imagen perfil/biografia',
                'index_hero_fallback_img' => 'Imagen fallback',
                'index_hero_party_img' => 'Logo/mascota flotante',
            ];
            foreach ($image_fields as $key => $label):
            ?>
            <div x-data="{ url: '<?= cfg_admin_val($config, $hero_defaults, $key) ?>' }">
              <label class="block text-xs font-black text-gray-500 uppercase mb-1"><?= htmlspecialchars($label) ?></label>
              <div class="flex gap-2">
                <input id="<?= $key ?>" name="<?= $key ?>" x-model="url" class="min-w-0 flex-1 border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono">
                <button type="button" @click="openMediaPicker((picked)=>{ url = picked }, 'image')" class="px-3 rounded-xl bg-indigo-50 text-indigo-600 text-xs font-bold border border-indigo-100">Media</button>
              </div>
              <div class="mt-2 h-24 rounded-xl bg-gray-50 border border-gray-100 overflow-hidden flex items-center justify-center">
                <img :src="url && url.startsWith('http') ? url : '<?= BASE_URL ?>' + url" class="max-h-full max-w-full object-contain" onerror="this.style.display='none'">
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
          <h2 class="text-sm font-black text-gray-800 mb-4">Badge flotante</h2>
          <label class="block text-xs font-black text-gray-500 uppercase mb-1">Titulo</label>
          <input name="index_hero_float_title" value="<?= cfg_admin_val($config, $hero_defaults, 'index_hero_float_title') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm mb-3">
          <label class="block text-xs font-black text-gray-500 uppercase mb-1">Subtitulo</label>
          <input name="index_hero_float_subtitle" value="<?= cfg_admin_val($config, $hero_defaults, 'index_hero_float_subtitle') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
        </div>
      </div>
    </div>

    <!-- Efecto visual candidato -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-4">
        <h2 class="text-white font-black text-base">Efecto visual sobre el candidato</h2>
        <p class="text-indigo-200 text-xs mt-0.5">Elige el efecto que aparecerá alrededor de la foto del candidato en el hero.</p>
      </div>
      <div class="p-6">
        <style>
          .hfx-prev { position:relative; overflow:hidden; border-radius:.75rem; background:#1E3A8A; height:5rem; }
          .hfx-prev .pr { position:absolute; border-radius:50%; pointer-events:none; top:50%; left:50%; aspect-ratio:1; }
          .hfx-prev-halo .pr1 { width:52%; border:1.5px solid rgba(56,189,248,.7); animation:pa 2s ease-in-out 0s    infinite; transform:translate(-50%,-50%); }
          .hfx-prev-halo .pr2 { width:74%; border:1.5px solid rgba(56,189,248,.4); animation:pa 2s ease-in-out .7s   infinite; transform:translate(-50%,-50%); }
          .hfx-prev-halo .pr3 { width:92%; border:1.5px solid rgba(56,189,248,.2); animation:pa 2s ease-in-out 1.4s  infinite; transform:translate(-50%,-50%); }
          @keyframes pa { 0%,100%{opacity:0;transform:translate(-50%,-50%) scale(.85)} 50%{opacity:1;transform:translate(-50%,-50%) scale(1)} }
          .hfx-prev-sparkles .sp { position:absolute; border-radius:50%; width:6px; height:6px; background:#38BDF8; box-shadow:0 0 5px 2px #38BDF888; animation:sb 2s ease-in-out var(--d,0s) infinite; }
          @keyframes sb { 0%,100%{opacity:0;transform:scale(0)} 50%{opacity:1;transform:scale(1) translateY(-8px)} }
          .hfx-prev-sweep::before { content:''; position:absolute; inset:0; background:linear-gradient(108deg,transparent 25%,rgba(255,255,255,.35) 50%,transparent 75%); animation:sw 2.5s ease-in-out infinite; transform:translateX(-110%); }
          @keyframes sw { 0%{transform:translateX(-110%)} 70%,100%{transform:translateX(110%)} }
          .hfx-prev-arcos .ar1 { width:55%; border:2px solid transparent; border-top-color:rgba(56,189,248,.9); border-bottom-color:rgba(56,189,248,.9); animation:ar 2s linear infinite; transform:translate(-50%,-50%); }
          .hfx-prev-arcos .ar2 { width:78%; border:1.5px solid transparent; border-left-color:rgba(250,204,21,.7); border-right-color:rgba(250,204,21,.7); animation:ar 3s linear reverse infinite; transform:translate(-50%,-50%); }
          @keyframes ar { to{transform:translate(-50%,-50%) rotate(360deg)} }
        </style>
        <?php
        $effects = [
          'none'     => ['Sin efecto',              'hfx-prev',                           '<span class="text-white/30 text-2xl">—</span>'],
          'halo'     => ['Halo pulsante',            'hfx-prev hfx-prev-halo',             '<div class="pr pr1"></div><div class="pr pr2"></div><div class="pr pr3"></div>'],
          'sparkles' => ['Destellos flotantes',      'hfx-prev hfx-prev-sparkles',         '<span class="sp" style="top:20%;left:15%;--d:0s"></span><span class="sp" style="top:50%;left:55%;--d:.5s;background:#FACC15;box-shadow:0 0 5px 2px #FACC1588"></span><span class="sp" style="top:25%;left:70%;--d:1s"></span><span class="sp" style="top:65%;left:30%;--d:1.5s;background:#7DD3FC;box-shadow:0 0 5px 2px #7DD3FC88"></span>'],
          'sweep'    => ['Barrido de luz',           'hfx-prev hfx-prev-sweep',            ''],
          'arcos'    => ['Arcos giratorios',         'hfx-prev hfx-prev-arcos',            '<div class="pr ar1"></div><div class="pr ar2"></div>'],
          'combo'          => ['Combo (halo + destellos)',  'hfx-prev hfx-prev-halo hfx-prev-sparkles',  '<div class="pr pr1"></div><div class="pr pr2"></div><span class="sp" style="top:18%;left:12%;--d:0s"></span><span class="sp" style="top:55%;left:65%;--d:.8s;background:#FACC15;box-shadow:0 0 5px 2px #FACC1588"></span><span class="sp" style="top:70%;left:20%;--d:1.5s"></span>'],
          'arcos_sparkles' => ['Arcos + Destellos',         'hfx-prev hfx-prev-arcos hfx-prev-sparkles', '<div class="pr ar1"></div><div class="pr ar2"></div><span class="sp" style="top:15%;left:18%;--d:0s"></span><span class="sp" style="top:60%;left:60%;--d:.7s;background:#FACC15;box-shadow:0 0 5px 2px #FACC1588"></span><span class="sp" style="top:75%;left:25%;--d:1.4s"></span>'],
        ];
        $current_fx = cfg_value($config, 'hero_candidate_effect', 'none');
        ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <?php foreach ($effects as $val => [$label, $cls, $inner]): ?>
          <label class="cursor-pointer select-none">
            <input type="radio" name="hero_candidate_effect" value="<?= $val ?>"
                   class="sr-only peer"
                   <?= $current_fx === $val ? 'checked' : '' ?>>
            <div class="peer-checked:ring-2 peer-checked:ring-violet-500 peer-checked:bg-violet-50
                        rounded-xl border border-gray-200 bg-white hover:border-violet-300
                        transition-all overflow-hidden cursor-pointer">
              <div class="<?= $cls ?> flex items-center justify-center"><?= $inner ?></div>
              <p class="text-center text-xs font-bold text-gray-700 py-2 px-1"><?= htmlspecialchars($label) ?></p>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <!-- /Efecto visual candidato -->

    <div class="sticky bottom-0 bg-[#F1F5F9]/95 backdrop-blur py-4 flex justify-end gap-3">
      <a href="<?= BASE_URL ?>/index.php" target="_blank" class="bg-white border border-gray-200 text-gray-600 font-bold px-5 py-3 rounded-xl text-sm">Ver portada</a>
      <button type="submit" class="bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-black px-8 py-3 rounded-xl text-sm shadow">
        Guardar Hero
      </button>
    </div>
  </form>

  <form method="POST" x-show="activeTab === 'badge'" class="space-y-6">
    <input type="hidden" name="tab" value="badge">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-[#1E3A8A] px-6 py-4">
          <h2 class="text-white font-bold text-sm">Pildora del partido</h2>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Letra / sigla</label>
            <input name="index_hero_badge_letter" value="<?= cfg_admin_val($config, $hero_defaults, 'index_hero_badge_letter') ?>" maxlength="4" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
          </div>
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Texto</label>
            <input name="index_hero_badge_label" value="<?= cfg_admin_val($config, $hero_defaults, 'index_hero_badge_label') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-[#1E3A8A] px-6 py-4">
          <h2 class="text-white font-bold text-sm">Badge flotante</h2>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Titulo</label>
            <input name="index_hero_float_title" value="<?= cfg_admin_val($config, $hero_defaults, 'index_hero_float_title') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
          </div>
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Subtitulo</label>
            <input name="index_hero_float_subtitle" value="<?= cfg_admin_val($config, $hero_defaults, 'index_hero_float_subtitle') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-[#1E3A8A] px-6 py-4">
          <h2 class="text-white font-bold text-sm">Estadisticas rapidas</h2>
        </div>
        <div class="p-6 space-y-3">
          <?php for ($i = 0; $i < 3; $i++): ?>
          <div class="grid grid-cols-3 gap-2">
            <input name="stat_n[]" value="<?= htmlspecialchars($hero_stats[$i]['n'] ?? '', ENT_QUOTES) ?>" placeholder="Nro" class="border border-gray-200 rounded-xl px-3 py-2 text-sm">
            <input name="stat_l[]" value="<?= htmlspecialchars($hero_stats[$i]['l'] ?? '', ENT_QUOTES) ?>" placeholder="Etiqueta" class="col-span-2 border border-gray-200 rounded-xl px-3 py-2 text-sm">
          </div>
          <?php endfor; ?>
        </div>
      </div>
    </div>

    <div class="sticky bottom-0 bg-[#F1F5F9]/95 backdrop-blur py-4 flex justify-end gap-3">
      <a href="<?= BASE_URL ?>/index.php" target="_blank" class="bg-white border border-gray-200 text-gray-600 font-bold px-5 py-3 rounded-xl text-sm">Ver portada</a>
      <button type="submit" class="bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-black px-8 py-3 rounded-xl text-sm shadow">
        Guardar Badges
      </button>
    </div>
  </form>

  <form method="POST" x-show="activeTab === 'hero2'" class="space-y-6">
    <input type="hidden" name="tab" value="hero2">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="bg-[#1E3A8A] px-6 py-4">
            <h2 class="text-white font-bold text-sm">Contenido de la Seccion II</h2>
          </div>
          <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Titulo linea 1</label>
              <input name="index_hero2_title_line1" value="<?= cfg_admin_val($config, $hero2_defaults, 'index_hero2_title_line1') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Texto resaltado</label>
              <input name="index_hero2_title_highlight" value="<?= cfg_admin_val($config, $hero2_defaults, 'index_hero2_title_highlight') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Titulo linea 3</label>
              <input name="index_hero2_title_line3" value="<?= cfg_admin_val($config, $hero2_defaults, 'index_hero2_title_line3') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Texto boton</label>
              <input name="index_hero2_button_text" value="<?= cfg_admin_val($config, $hero2_defaults, 'index_hero2_button_text') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Descripcion</label>
              <textarea name="index_hero2_text" rows="4" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm"><?= cfg_admin_val($config, $hero2_defaults, 'index_hero2_text') ?></textarea>
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">URL boton</label>
              <input name="index_hero2_button_url" value="<?= cfg_admin_val($config, $hero2_defaults, 'index_hero2_button_url') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono">
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="bg-[#1E3A8A] px-6 py-4">
            <h2 class="text-white font-bold text-sm">Tres pilares destacados</h2>
          </div>
          <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php for ($i = 0; $i < 3; $i++): ?>
            <div class="bg-gray-50 border border-gray-100 rounded-2xl p-4 space-y-3">
              <label class="block text-xs font-black text-gray-500 uppercase">Pilar <?= $i + 1 ?></label>
              <input name="pillar_icon[]" value="<?= htmlspecialchars($hero2_pillars[$i]['icon'] ?? '', ENT_QUOTES) ?>" placeholder="Icono: obra, gestion, campo" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm">
              <input name="pillar_title[]" value="<?= htmlspecialchars($hero2_pillars[$i]['title'] ?? '', ENT_QUOTES) ?>" placeholder="Titulo" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm">
              <textarea name="pillar_desc[]" rows="3" placeholder="Descripcion" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm"><?= htmlspecialchars($hero2_pillars[$i]['desc'] ?? '', ENT_QUOTES) ?></textarea>
            </div>
            <?php endfor; ?>
          </div>
        </div>
      </div>

      <div class="space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="bg-[#1E3A8A] px-6 py-4">
            <h2 class="text-white font-bold text-sm">Fondo visual</h2>
          </div>
          <div class="p-6" x-data="{ url: '<?= cfg_admin_val($config, $hero2_defaults, 'index_hero2_bg_img') ?>' }">
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Imagen de fondo</label>
            <div class="flex gap-2">
              <input name="index_hero2_bg_img" x-model="url" class="min-w-0 flex-1 border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono">
              <button type="button" @click="openMediaPicker((picked)=>{ url = picked }, 'image')" class="px-3 rounded-xl bg-indigo-50 text-indigo-600 text-xs font-bold border border-indigo-100">Media</button>
            </div>
            <div class="mt-3 aspect-video rounded-2xl bg-gray-100 border border-gray-100 overflow-hidden">
              <img :src="url && url.startsWith('http') ? url : '<?= BASE_URL ?>' + url" class="w-full h-full object-cover" onerror="this.style.display='none'">
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="sticky bottom-0 bg-[#F1F5F9]/95 backdrop-blur py-4 flex justify-end gap-3">
      <a href="<?= BASE_URL ?>/index.php" target="_blank" class="bg-white border border-gray-200 text-gray-600 font-bold px-5 py-3 rounded-xl text-sm">Ver portada</a>
      <button type="submit" class="bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-black px-8 py-3 rounded-xl text-sm shadow">
        Guardar Seccion II
      </button>
    </div>
  </form>

  <form method="POST" x-show="activeTab === 'bio'" class="space-y-6">
    <input type="hidden" name="tab" value="bio">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="bg-[#1E3A8A] px-6 py-4">
            <h2 class="text-white font-bold text-sm">Textos de biografia corta</h2>
          </div>
          <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Etiqueta superior</label>
              <input name="index_bio_eyebrow" value="<?= cfg_admin_val($config, $bio_defaults, 'index_bio_eyebrow') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Titulo</label>
              <input name="index_bio_title" value="<?= cfg_admin_val($config, $bio_defaults, 'index_bio_title') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
              <p class="text-xs text-gray-400 mt-1">Usa | para salto de linea.</p>
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Parrafo 1</label>
              <textarea name="index_bio_p1" rows="4" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm"><?= cfg_admin_val($config, $bio_defaults, 'index_bio_p1') ?></textarea>
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Parrafo 2</label>
              <textarea name="index_bio_p2" rows="4" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm"><?= cfg_admin_val($config, $bio_defaults, 'index_bio_p2') ?></textarea>
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Texto boton</label>
              <input name="index_bio_button_text" value="<?= cfg_admin_val($config, $bio_defaults, 'index_bio_button_text') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">URL boton</label>
              <input name="index_bio_button_url" value="<?= cfg_admin_val($config, $bio_defaults, 'index_bio_button_url') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono">
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="bg-[#1E3A8A] px-6 py-4">
            <h2 class="text-white font-bold text-sm">Atributos destacados</h2>
          </div>
          <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php for ($i = 0; $i < 3; $i++): ?>
            <div class="bg-gray-50 border border-gray-100 rounded-2xl p-4 space-y-3">
              <label class="block text-xs font-black text-gray-500 uppercase">Atributo <?= $i + 1 ?></label>
              <input name="bio_attr_icon[]" value="<?= htmlspecialchars($bio_attrs[$i]['icon'] ?? '', ENT_QUOTES) ?>" placeholder="compromiso, experiencia, cercania" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm">
              <input name="bio_attr_title[]" value="<?= htmlspecialchars($bio_attrs[$i]['title'] ?? '', ENT_QUOTES) ?>" placeholder="Titulo" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm">
              <textarea name="bio_attr_desc[]" rows="3" placeholder="Descripcion" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm"><?= htmlspecialchars($bio_attrs[$i]['desc'] ?? '', ENT_QUOTES) ?></textarea>
            </div>
            <?php endfor; ?>
          </div>
        </div>
      </div>

      <div class="space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="bg-[#1E3A8A] px-6 py-4">
            <h2 class="text-white font-bold text-sm">Imagen y badge</h2>
          </div>
          <div class="p-6 space-y-4">
            <div x-data="{ url: '<?= cfg_admin_val($config, $bio_defaults, 'index_bio_img') ?>' }">
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Imagen principal</label>
              <div class="flex gap-2">
                <input name="index_bio_img" x-model="url" class="min-w-0 flex-1 border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono">
                <button type="button" @click="openMediaPicker((picked)=>{ url = picked }, 'image')" class="px-3 rounded-xl bg-indigo-50 text-indigo-600 text-xs font-bold border border-indigo-100">Media</button>
              </div>
              <div class="mt-3 aspect-video rounded-2xl bg-gray-100 border border-gray-100 overflow-hidden">
                <img :src="url && url.startsWith('http') ? url : '<?= BASE_URL ?>' + url" class="w-full h-full object-cover" onerror="this.style.display='none'">
              </div>
            </div>

            <div x-data="{ url: '<?= cfg_admin_val($config, $bio_defaults, 'index_bio_fallback_img') ?>' }">
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Imagen fallback</label>
              <div class="flex gap-2">
                <input name="index_bio_fallback_img" x-model="url" class="min-w-0 flex-1 border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono">
                <button type="button" @click="openMediaPicker((picked)=>{ url = picked }, 'image')" class="px-3 rounded-xl bg-indigo-50 text-indigo-600 text-xs font-bold border border-indigo-100">Media</button>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Numero badge</label>
                <input name="index_bio_badge_number" value="<?= cfg_admin_val($config, $bio_defaults, 'index_bio_badge_number') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
              </div>
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Texto badge</label>
                <input name="index_bio_badge_label" value="<?= cfg_admin_val($config, $bio_defaults, 'index_bio_badge_label') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="sticky bottom-0 bg-[#F1F5F9]/95 backdrop-blur py-4 flex justify-end gap-3">
      <a href="<?= BASE_URL ?>/index.php" target="_blank" class="bg-white border border-gray-200 text-gray-600 font-bold px-5 py-3 rounded-xl text-sm">Ver portada</a>
      <button type="submit" class="bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-black px-8 py-3 rounded-xl text-sm shadow">
        Guardar Biografia
      </button>
    </div>
  </form>

  <!-- Ejes de Trabajo movido a config-plan.php -->
  <div x-show="activeTab === 'ejes'"
       class="rounded-2xl border border-blue-200 bg-blue-50 p-8 text-center">
    <svg class="w-10 h-10 text-[#1E3A8A] mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
    </svg>
    <p class="font-black text-[#1E3A8A] text-base mb-1">Los Ejes de Trabajo ahora se gestionan desde el módulo <em>Plan de Gobierno</em>.</p>
    <p class="text-sm text-blue-600 mb-5">Se mantienen los datos existentes — solo cambió la ubicación del editor.</p>
    <a href="<?= BASE_URL ?>/admin/config-plan.php"
       class="inline-flex items-center gap-2 bg-[#1E3A8A] hover:bg-blue-900 text-white font-black px-6 py-3 rounded-xl text-sm transition-all shadow-md">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
      </svg>
      Ir a Plan de Gobierno
    </a>
  </div>

  <div style="display:none" aria-hidden="true"><!-- ejes movido a config-plan.php -->

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-[#1E3A8A] px-6 py-4">
          <h2 class="text-white font-bold text-sm">Encabezado de la seccion</h2>
        </div>
        <div class="p-6">
          <div class="config-group">
            <div class="config-group-title">
              <div>
                <h3>Textos visibles en portada</h3>
                <p>Controla la etiqueta, titulo y descripcion que aparecen sobre las tarjetas de ejes.</p>
              </div>
            </div>
            <div class="config-field-grid two">
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Etiqueta superior</label>
                <input name="index_work_eyebrow" value="<?= cfg_admin_val($config, $work_section_defaults, 'index_work_eyebrow') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
              </div>
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Titulo</label>
                <input name="index_work_title" value="<?= cfg_admin_val($config, $work_section_defaults, 'index_work_title') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
              </div>
              <div class="sm:col-span-2">
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Descripcion</label>
                <textarea name="index_work_text" rows="3" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm"><?= cfg_admin_val($config, $work_section_defaults, 'index_work_text') ?></textarea>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-[#1E3A8A] px-6 py-4">
          <h2 class="text-white font-bold text-sm">Boton del plan</h2>
        </div>
        <div class="p-6">
          <div class="config-group">
            <div class="config-group-title">
              <div>
                <h3>Llamado a la accion</h3>
                <p>Boton inferior de la seccion de ejes.</p>
              </div>
            </div>
            <div class="space-y-4">
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Texto boton</label>
                <input name="index_work_button_text" value="<?= cfg_admin_val($config, $work_section_defaults, 'index_work_button_text') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
              </div>
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">URL boton</label>
                <input name="index_work_button_url" value="<?= cfg_admin_val($config, $work_section_defaults, 'index_work_button_url') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono">
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="bg-[#1E3A8A] px-6 py-4 flex items-center justify-between gap-3">
        <h2 class="text-white font-bold text-sm">Ejes de Trabajo</h2>
        <button type="button"
                @click="addAxis()"
                class="bg-white/15 hover:bg-white/25 text-white text-xs font-black px-3 py-2 rounded-lg">
          + Agregar eje
        </button>
      </div>

      <div class="p-6 space-y-5">
        <div x-show="axes.length === 0" class="rounded-2xl border border-dashed border-blue-200 bg-blue-50 p-6 text-center">
          <p class="text-sm font-black text-[#0F2057]">Aun no hay ejes configurados.</p>
          <p class="text-xs text-blue-700 mt-1">Pulsa "Agregar eje" para crear el primer bloque de trabajo.</p>
        </div>
        <template x-for="(axis, i) in axes" :key="i">
          <div class="rounded-2xl border border-gray-200 bg-gray-50 overflow-hidden">
            <div class="px-4 py-3 bg-white border-b border-gray-100 flex items-center justify-between gap-3">
              <div>
                <p class="text-sm font-black text-gray-800" x-text="axis.title || 'Eje de trabajo'"></p>
                <p class="text-xs text-gray-400">Se muestra en portada y plan.php</p>
              </div>
              <button type="button" @click="axes.splice(i, 1)" class="text-red-500 hover:text-red-700 text-xs font-bold">Quitar</button>
            </div>

            <div class="p-4 grid grid-cols-1 lg:grid-cols-3 gap-4">
              <div class="space-y-3">
                <label class="block text-xs font-black text-gray-500 uppercase">Identificador</label>
                <input name="eje_id[]" x-model="axis.id" placeholder="seguridad" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm font-mono">

                <label class="block text-xs font-black text-gray-500 uppercase">Icono</label>
                <select name="eje_icon[]" x-model="axis.icon" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm">
                  <option value="seguridad">Seguridad</option>
                  <option value="obra">Obra</option>
                  <option value="campo">Campo</option>
                  <option value="salud">Salud</option>
                  <option value="educacion">Educacion</option>
                  <option value="comunidad">Comunidad</option>
                  <option value="agua">Agua</option>
                  <option value="transporte">Transporte</option>
                  <option value="economia">Economia</option>
                  <option value="turismo">Turismo</option>
                  <option value="deporte">Deporte</option>
                  <option value="ambiente">Ambiente</option>
                  <option value="juventud">Juventud</option>
                  <option value="gestion">Gestion</option>
                </select>
                <button type="button" @click="applyPreset(axis)" class="w-full rounded-xl border border-blue-100 bg-blue-50 px-3 py-2 text-xs font-black text-blue-700 hover:bg-blue-100">
                  Detectar icono automaticamente
                </button>

                <label class="block text-xs font-black text-gray-500 uppercase">Imagen plan</label>
                <div class="flex gap-2">
                  <input name="eje_img[]" x-model="axis.img" class="min-w-0 flex-1 border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono">
                  <button type="button" @click="openMediaPicker((picked)=>{ axis.img = picked }, 'image')" class="px-3 rounded-xl bg-indigo-50 text-indigo-600 text-xs font-bold border border-indigo-100">Media</button>
                </div>
              </div>

              <div class="space-y-3">
                <label class="block text-xs font-black text-gray-500 uppercase">Etiqueta menu</label>
                <input name="eje_label[]" x-model="axis.label" @input.debounce.500ms="applyPreset(axis)" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm">

                <label class="block text-xs font-black text-gray-500 uppercase">Titulo</label>
                <input name="eje_title[]" x-model="axis.title" @input.debounce.500ms="applyPreset(axis)" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm">

                <label class="block text-xs font-black text-gray-500 uppercase">Descripcion portada</label>
                <textarea name="eje_desc[]" x-model="axis.desc" rows="3" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm"></textarea>
              </div>

              <div class="space-y-3">
                <label class="block text-xs font-black text-gray-500 uppercase">Gradiente portada</label>
                <input name="eje_grad[]" x-model="axis.grad" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono">

                <div class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="block text-xs font-black text-gray-500 uppercase">Nav color</label>
                    <input name="eje_nav_color[]" x-model="axis.nav_color" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono">
                  </div>
                  <div>
                    <label class="block text-xs font-black text-gray-500 uppercase">Nav borde</label>
                    <input name="eje_nav_border[]" x-model="axis.nav_border" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono">
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="block text-xs font-black text-gray-500 uppercase">Fondo plan</label>
                    <input name="eje_section_bg[]" x-model="axis.section_bg" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono">
                  </div>
                  <div>
                    <label class="block text-xs font-black text-gray-500 uppercase">Glow plan</label>
                    <input name="eje_section_border[]" x-model="axis.section_border" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono">
                  </div>
                </div>
              </div>

              <div class="lg:col-span-3 grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-black text-gray-500 uppercase mb-1">Descripcion en plan.php</label>
                  <textarea name="eje_plan_desc[]" x-model="axis.plan_desc" rows="5" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm"></textarea>
                  <p class="text-xs text-gray-400 mt-1">Un parrafo por linea.</p>
                </div>
                <div>
                  <label class="block text-xs font-black text-gray-500 uppercase mb-1">Propuestas concretas</label>
                  <textarea name="eje_proposals[]" x-model="axis.proposals" rows="5" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm"></textarea>
                  <p class="text-xs text-gray-400 mt-1">Una propuesta por linea.</p>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>

    <div class="sticky bottom-0 bg-[#F1F5F9]/95 backdrop-blur py-4 flex justify-end gap-3">
      <a href="<?= BASE_URL ?>/plan.php" target="_blank" class="bg-white border border-gray-200 text-gray-600 font-bold px-5 py-3 rounded-xl text-sm">Ver plan</a>
    </div>
  </div><!-- fin bloque ejes oculto -->

  <form method="POST" x-show="activeTab === 'bloques'" class="space-y-6">
    <input type="hidden" name="tab" value="bloques">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-[#1E3A8A] px-6 py-4">
          <h2 class="text-white font-bold text-sm">Ultimas Noticias</h2>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Etiqueta</label>
            <input name="index_news_eyebrow" value="<?= cfg_admin_val($config, $blocks_defaults, 'index_news_eyebrow') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
          </div>
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Titulo</label>
            <input name="index_news_title" value="<?= cfg_admin_val($config, $blocks_defaults, 'index_news_title') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
          </div>
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Descripcion</label>
            <textarea name="index_news_text" rows="3" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm"><?= cfg_admin_val($config, $blocks_defaults, 'index_news_text') ?></textarea>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Boton</label>
              <input name="index_news_button_text" value="<?= cfg_admin_val($config, $blocks_defaults, 'index_news_button_text') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">URL</label>
              <input name="index_news_button_url" value="<?= cfg_admin_val($config, $blocks_defaults, 'index_news_button_url') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono">
            </div>
          </div>
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Mensaje sin noticias</label>
            <textarea name="index_news_empty_text" rows="2" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm"><?= cfg_admin_val($config, $blocks_defaults, 'index_news_empty_text') ?></textarea>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-[#1E3A8A] px-6 py-4">
          <h2 class="text-white font-bold text-sm">Formulario Unete</h2>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Titulo</label>
            <input name="index_join_title" value="<?= cfg_admin_val($config, $blocks_defaults, 'index_join_title') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
            <p class="text-xs text-gray-400 mt-1">Usa | para salto de linea.</p>
          </div>
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Descripcion</label>
            <textarea name="index_join_text" rows="3" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm"><?= cfg_admin_val($config, $blocks_defaults, 'index_join_text') ?></textarea>
          </div>
          <?php
          $join_fields = [
              'index_join_name_label' => 'Label nombre',
              'index_join_name_placeholder' => 'Placeholder nombre',
              'index_join_dni_label' => 'Label DNI',
              'index_join_phone_label' => 'Label telefono',
              'index_join_district_label' => 'Label distrito',
              'index_join_district_placeholder' => 'Placeholder distrito',
              'index_join_button_text' => 'Boton',
          ];
          foreach ($join_fields as $key => $label):
          ?>
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1"><?= htmlspecialchars($label) ?></label>
            <input name="<?= $key ?>" value="<?= cfg_admin_val($config, $blocks_defaults, $key) ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-[#1E3A8A] px-6 py-4">
          <h2 class="text-white font-bold text-sm">Calendario de Actividades</h2>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Etiqueta</label>
            <input name="index_agenda_eyebrow" value="<?= cfg_admin_val($config, $blocks_defaults, 'index_agenda_eyebrow') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
          </div>
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Titulo</label>
            <input name="index_agenda_title" value="<?= cfg_admin_val($config, $blocks_defaults, 'index_agenda_title') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
          </div>
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Descripcion</label>
            <textarea name="index_agenda_text" rows="3" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm"><?= cfg_admin_val($config, $blocks_defaults, 'index_agenda_text') ?></textarea>
          </div>
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Mensaje sin actividades</label>
            <textarea name="index_agenda_empty_text" rows="3" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm"><?= cfg_admin_val($config, $blocks_defaults, 'index_agenda_empty_text') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="sticky bottom-0 bg-[#F1F5F9]/95 backdrop-blur py-4 flex justify-end gap-3">
      <a href="<?= BASE_URL ?>/index.php#noticias" target="_blank" class="bg-white border border-gray-200 text-gray-600 font-bold px-5 py-3 rounded-xl text-sm">Ver portada</a>
      <button type="submit" class="bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-black px-8 py-3 rounded-xl text-sm shadow">
        Guardar Bloques
      </button>
    </div>
  </form>

  <form method="POST" x-show="activeTab === 'social'" class="space-y-6">
    <input type="hidden" name="tab" value="social">

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-[#1E3A8A] px-6 py-4">
          <h2 class="text-white font-bold text-sm">Bloque izquierdo</h2>
        </div>
        <div class="p-6 space-y-4">
          <label class="inline-flex items-center gap-3 text-sm font-bold text-gray-700">
            <input type="checkbox" name="index_social_enabled" value="1"
                   <?= cfg_value($config, 'index_social_enabled', $social_defaults['index_social_enabled']) === '1' ? 'checked' : '' ?>
                   class="w-4 h-4 rounded border-gray-300 text-[#1E3A8A] focus:ring-[#1E3A8A]">
            Mostrar seccion en portada
          </label>

          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Etiqueta superior</label>
            <input name="index_social_eyebrow" value="<?= cfg_admin_val($config, $social_defaults, 'index_social_eyebrow') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
          </div>
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Titulo</label>
            <input name="index_social_title" value="<?= cfg_admin_val($config, $social_defaults, 'index_social_title') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
          </div>
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Subtitulo</label>
            <input name="index_social_subtitle" value="<?= cfg_admin_val($config, $social_defaults, 'index_social_subtitle') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
          </div>
          <div x-data="{ url: '<?= cfg_admin_val($config, $social_defaults, 'index_social_image') ?>' }">
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Imagen candidato / campana</label>
            <div class="flex gap-2">
              <input name="index_social_image" x-model="url" class="min-w-0 flex-1 border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono">
              <button type="button" @click="openMediaPicker((picked)=>{ url = picked }, 'image')" class="px-3 rounded-xl bg-indigo-50 text-indigo-600 text-xs font-bold border border-indigo-100">Media</button>
            </div>
            <div class="mt-3 border border-gray-100 rounded-xl bg-gray-50 overflow-hidden">
              <img :src="url" alt="" class="w-full h-40 object-cover object-top" onerror="this.style.display='none'">
            </div>
          </div>
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Alt imagen</label>
            <input name="index_social_image_alt" value="<?= cfg_admin_val($config, $social_defaults, 'index_social_image_alt') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Texto boton</label>
              <input name="index_social_button_text" value="<?= cfg_admin_val($config, $social_defaults, 'index_social_button_text') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">URL boton</label>
              <input name="index_social_button_url" value="<?= cfg_admin_val($config, $social_defaults, 'index_social_button_url') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono">
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-[#1E3A8A] px-6 py-4">
          <h2 class="text-white font-bold text-sm">Timeline Facebook</h2>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">URL fanpage</label>
            <input name="index_social_facebook_url" value="<?= cfg_admin_val($config, $social_defaults, 'index_social_facebook_url') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono">
          </div>
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Alto del timeline</label>
            <input type="number" min="280" max="900" name="index_social_plugin_height" value="<?= cfg_admin_val($config, $social_defaults, 'index_social_plugin_height') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
            <p class="text-xs text-gray-400 mt-1">Facebook actualiza automaticamente el contenido publicado en la fanpage.</p>
          </div>
          <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-800 leading-relaxed">
            El lado derecho usa el plugin oficial de Facebook. No requiere token ni cargar URLs de publicaciones; muestra el timeline publico de la pagina configurada.
          </div>
        </div>
      </div>
    </div>

    <div class="sticky bottom-0 bg-[#F1F5F9]/95 backdrop-blur py-4 flex justify-end gap-3">
      <a href="<?= BASE_URL ?>/index.php#redes-sociales" target="_blank" class="bg-white border border-gray-200 text-gray-600 font-bold px-5 py-3 rounded-xl text-sm">Ver seccion</a>
      <button type="submit" class="bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-black px-8 py-3 rounded-xl text-sm shadow">
        Guardar Redes
      </button>
    </div>
  </form>

  <form method="POST" x-show="activeTab === 'contacto'" class="space-y-6">
    <input type="hidden" name="tab" value="contacto">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-[#1E3A8A] px-6 py-4">
          <h2 class="text-white font-bold text-sm">Contactenos</h2>
        </div>
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Etiqueta superior</label>
            <input name="index_contact_eyebrow" value="<?= cfg_admin_val($config, $contact_defaults, 'index_contact_eyebrow') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
          </div>
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Titulo</label>
            <input name="index_contact_title" value="<?= cfg_admin_val($config, $contact_defaults, 'index_contact_title') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
            <p class="text-xs text-gray-400 mt-1">Usa | para salto de linea.</p>
          </div>

          <?php
          $contact_fields = [
              'index_contact_address_label' => 'Label direccion',
              'index_contact_address_line1' => 'Direccion linea 1',
              'index_contact_address_line2' => 'Direccion linea 2',
              'index_contact_hours_label' => 'Label horario',
              'index_contact_hours_line1' => 'Horario linea 1',
              'index_contact_hours_line2' => 'Horario linea 2',
              'index_contact_phone_label' => 'Label telefono',
              'index_contact_phone_text' => 'Telefono visible',
              'index_contact_phone_href' => 'Enlace telefono',
              'index_contact_button_text' => 'Boton mapa',
              'index_contact_button_url' => 'URL boton mapa',
              'index_contact_map_title' => 'Titulo iframe',
          ];
          foreach ($contact_fields as $key => $label):
          ?>
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1"><?= htmlspecialchars($label) ?></label>
            <input name="<?= $key ?>" value="<?= cfg_admin_val($config, $contact_defaults, $key) ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm <?= str_contains($key, 'url') || str_contains($key, 'href') ? 'font-mono' : '' ?>">
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-[#1E3A8A] px-6 py-4">
          <h2 class="text-white font-bold text-sm">Mapa Google Maps</h2>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Iframe completo o URL src</label>
            <textarea name="index_contact_map_iframe" rows="9" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-xs font-mono"><?= cfg_admin_val($config, $contact_defaults, 'index_contact_map_iframe') ?></textarea>
            <p class="text-xs text-gray-400 mt-1">Puedes pegar el iframe de Google Maps o solo el valor src.</p>
          </div>
        </div>
      </div>
    </div>

    <div class="sticky bottom-0 bg-[#F1F5F9]/95 backdrop-blur py-4 flex justify-end gap-3">
      <a href="<?= BASE_URL ?>/index.php#local" target="_blank" class="bg-white border border-gray-200 text-gray-600 font-bold px-5 py-3 rounded-xl text-sm">Ver contacto</a>
      <button type="submit" class="bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-black px-8 py-3 rounded-xl text-sm shadow">
        Guardar Contacto
      </button>
    </div>
  </form>

  <form method="POST" x-show="activeTab === 'header_footer'" class="space-y-6">
    <input type="hidden" name="tab" value="header_footer">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="config-card">
        <div class="config-card-head">
          <h2>Header</h2>
        </div>
        <div class="p-6 space-y-4">
          <div class="config-group">
            <div class="config-group-title">
              <div>
                <h3>Identidad visual</h3>
                <p>Logo y firma que aparecen en la barra superior del sitio.</p>
              </div>
            </div>
            <div class="space-y-4">
              <div x-data="{ url: '<?= cfg_admin_val($config, $hf_defaults, 'site_header_logo') ?>' }">
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Logo principal</label>
                <div class="flex gap-2">
                  <input name="site_header_logo" x-model="url" class="min-w-0 flex-1 border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono">
                  <button type="button" @click="openMediaPicker((picked)=>{ url = picked }, 'image')" class="px-3 rounded-xl bg-indigo-50 text-indigo-600 text-xs font-bold border border-indigo-100">Media</button>
                </div>
              </div>
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Alt logo</label>
                <input name="site_header_logo_alt" value="<?= cfg_admin_val($config, $hf_defaults, 'site_header_logo_alt') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
              </div>
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Firma / texto cursiva</label>
                <input name="site_header_signature" value="<?= cfg_admin_val($config, $hf_defaults, 'site_header_signature') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
              </div>
            </div>
          </div>

          <div class="config-group">
            <div class="config-group-title">
              <div>
                <h3>Boton principal</h3>
                <p>Texto y destino del llamado a la accion del menu superior.</p>
              </div>
            </div>
            <div class="config-field-grid two">
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">CTA texto</label>
                <input name="site_header_cta_text" value="<?= cfg_admin_val($config, $hf_defaults, 'site_header_cta_text') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
              </div>
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">CTA URL</label>
                <input name="site_header_cta_url" value="<?= cfg_admin_val($config, $hf_defaults, 'site_header_cta_url') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono">
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="config-card">
        <div class="config-card-head">
          <h2>PreFooter / Marca</h2>
        </div>
        <div class="p-6 space-y-4">
          <div class="config-group">
            <div class="config-group-title">
              <div>
                <h3>Marca del pie</h3>
                <p>Logo, nombre y frase institucional que aparecen en el footer.</p>
              </div>
            </div>
            <div class="space-y-4">
              <div x-data="{ url: '<?= cfg_admin_val($config, $hf_defaults, 'site_footer_logo') ?>' }">
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Logo footer</label>
                <div class="flex gap-2">
                  <input name="site_footer_logo" x-model="url" class="min-w-0 flex-1 border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono">
                  <button type="button" @click="openMediaPicker((picked)=>{ url = picked }, 'image')" class="px-3 rounded-xl bg-indigo-50 text-indigo-600 text-xs font-bold border border-indigo-100">Media</button>
                </div>
              </div>
              <?php
              $brand_identity_fields = [
                  'site_footer_name' => 'Nombre principal',
                  'site_footer_party' => 'Partido / subtitulo',
                  'site_footer_slogan' => 'Frase',
              ];
              foreach ($brand_identity_fields as $key => $label):
              ?>
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1"><?= htmlspecialchars($label) ?></label>
                <input name="<?= $key ?>" value="<?= cfg_admin_val($config, $hf_defaults, $key) ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="config-group">
            <div class="config-group-title">
              <div>
                <h3>Titulos de columnas</h3>
                <p>Nombres de las columnas de navegacion, equipo y contacto.</p>
              </div>
            </div>
            <div class="space-y-4">
              <?php
              $brand_column_fields = [
                  'site_footer_nav_title' => 'Titulo columna navegacion',
                  'site_footer_team_title' => 'Titulo columna equipo',
                  'site_footer_contact_title' => 'Titulo columna contacto',
              ];
              foreach ($brand_column_fields as $key => $label):
              ?>
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1"><?= htmlspecialchars($label) ?></label>
                <input name="<?= $key ?>" value="<?= cfg_admin_val($config, $hf_defaults, $key) ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="config-card">
        <div class="config-card-head">
          <h2>Footer</h2>
        </div>
        <div class="p-6 space-y-4">
          <div class="config-group">
            <div class="config-group-title">
              <div>
                <h3>Datos de contacto</h3>
                <p>Direccion y correo que se muestran en la columna de contacto.</p>
              </div>
            </div>
            <div class="space-y-4">
              <?php foreach (['site_footer_address' => 'Direccion', 'site_footer_email' => 'Email'] as $key => $label): ?>
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1"><?= htmlspecialchars($label) ?></label>
                <input name="<?= $key ?>" value="<?= cfg_admin_val($config, $hf_defaults, $key) ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="config-group">
            <div class="config-group-title">
              <div>
                <h3>Boton flotante de WhatsApp</h3>
                <p>Configura el boton flotante y el acceso WhatsApp del footer.</p>
              </div>
            </div>
            <div class="space-y-4">
              <?php
              $whatsapp_fields = [
                  'site_footer_whatsapp_text' => 'Texto WhatsApp',
                  'site_footer_whatsapp_number' => 'Numero WhatsApp',
                  'site_footer_whatsapp_message' => 'Mensaje prellenado WhatsApp',
                  'site_footer_whatsapp_url' => 'URL WhatsApp manual',
              ];
              foreach ($whatsapp_fields as $key => $label):
              ?>
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1"><?= htmlspecialchars($label) ?></label>
                <input name="<?= $key ?>" value="<?= cfg_admin_val($config, $hf_defaults, $key) ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm <?= str_contains($key, 'url') ? 'font-mono' : '' ?>">
                <?php if ($key === 'site_footer_whatsapp_url'): ?>
                <p class="text-xs text-gray-400 mt-1">Opcional. Si configuras numero y mensaje, esta URL manual no se usa.</p>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="config-group">
            <div class="config-group-title">
              <div>
                <h3>Redes sociales</h3>
                <p>Enlaces de iconos sociales mostrados en el footer.</p>
              </div>
            </div>
            <div class="space-y-4">
              <?php
              $social_footer_fields = [
                  'site_footer_facebook_url' => 'Facebook',
                  'site_footer_instagram_url' => 'Instagram',
                  'site_footer_tiktok_url' => 'TikTok',
                  'site_footer_youtube_url' => 'YouTube',
              ];
              foreach ($social_footer_fields as $key => $label):
              ?>
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1"><?= htmlspecialchars($label) ?></label>
                <input name="<?= $key ?>" value="<?= cfg_admin_val($config, $hf_defaults, $key) ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono">
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="config-group">
            <div class="config-group-title">
              <div>
                <h3>Legal y barra inferior</h3>
                <p>Textos finales de copyright y creditos del sitio.</p>
              </div>
            </div>
            <div class="space-y-4">
              <?php
              $legal_footer_fields = [
                  'site_footer_copyright' => 'Copyright',
                  'site_footer_bottom_left' => 'Barra inferior izquierda',
                  'site_footer_bottom_right' => 'Barra inferior derecha',
              ];
              foreach ($legal_footer_fields as $key => $label):
              ?>
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1"><?= htmlspecialchars($label) ?></label>
                <input name="<?= $key ?>" value="<?= cfg_admin_val($config, $hf_defaults, $key) ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Banner CTA Candidatos Distritales ─────────────── -->
      <div class="config-card">
        <div class="config-card-head">
          <h2>Banner de equipo — Paginas distritales</h2>
          <p class="text-xs text-gray-400 mt-0.5">Aparece al final de cada pagina de candidato distrital.</p>
        </div>
        <div class="p-6 space-y-4">

          <div class="config-group">
            <div class="config-group-title">
              <div>
                <h3>Foto y nombre del candidato provincial</h3>
                <p>Imagen circular y nombre destacado en amarillo.</p>
              </div>
            </div>
            <div class="space-y-4">
              <div x-data="{ url: '<?= htmlspecialchars(cfg_admin_val($config, $hf_defaults, 'dist_cta_photo'), ENT_QUOTES) ?>' }">
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Foto del candidato provincial</label>
                <div class="flex gap-2">
                  <input name="dist_cta_photo" x-model="url" class="min-w-0 flex-1 border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono">
                  <button type="button" @click="openMediaPicker((picked)=>{ url = picked }, 'image')" class="px-3 rounded-xl bg-indigo-50 text-indigo-600 text-xs font-bold border border-indigo-100">Media</button>
                </div>
                <div class="mt-2">
                  <img :src="url.match(/^https?:\/\//) ? url : '<?= BASE_URL ?>/' + url.replace(/^\/+/,'')"
                       alt="Preview foto"
                       class="w-14 h-14 rounded-full object-cover border-2 border-yellow-400"
                       onerror="this.style.display='none'" style="display:block">
                </div>
              </div>
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Nombre en amarillo</label>
                <input name="dist_cta_name" value="<?= htmlspecialchars(cfg_admin_val($config, $hf_defaults, 'dist_cta_name')) ?>"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
                <p class="text-xs text-gray-400 mt-1">Ejemplo: <em>Ivan Cisneros</em></p>
              </div>
            </div>
          </div>

          <div class="config-group">
            <div class="config-group-title">
              <div>
                <h3>Textos del banner</h3>
                <p>Frase principal y texto descriptivo debajo.</p>
              </div>
            </div>
            <div class="space-y-4">
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Texto antes del nombre</label>
                <input name="dist_cta_label" value="<?= htmlspecialchars(cfg_admin_val($config, $hf_defaults, 'dist_cta_label')) ?>"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm"
                       placeholder="es parte del equipo de">
                <p class="text-xs text-gray-400 mt-1">Aparece como: <em>[Candidato] <strong>es parte del equipo de</strong> Ivan Cisneros</em></p>
              </div>
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Texto descriptivo</label>
                <textarea name="dist_cta_text" rows="2"
                          class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm resize-none"
                          placeholder="Juntos trabajamos por..."><?= htmlspecialchars(cfg_admin_val($config, $hf_defaults, 'dist_cta_text')) ?></textarea>
              </div>
            </div>
          </div>

          <div class="config-group">
            <div class="config-group-title">
              <div>
                <h3>Boton de accion</h3>
                <p>Boton amarillo al lado derecho del banner.</p>
              </div>
            </div>
            <div class="config-field-grid two">
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Texto del boton</label>
                <input name="dist_cta_button" value="<?= htmlspecialchars(cfg_admin_val($config, $hf_defaults, 'dist_cta_button')) ?>"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm"
                       placeholder="Plan Provincial →">
              </div>
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">URL del boton</label>
                <input name="dist_cta_url" value="<?= htmlspecialchars(cfg_admin_val($config, $hf_defaults, 'dist_cta_url')) ?>"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono"
                       placeholder="/plan.php">
              </div>
            </div>
          </div>

        </div>
      </div>
      <!-- ── /Banner CTA ─────────────────────────────────────── -->

    </div>

    <div class="sticky bottom-0 bg-[#F1F5F9]/95 backdrop-blur py-4 flex justify-end gap-3">
      <a href="<?= BASE_URL ?>/index.php" target="_blank" class="bg-white border border-gray-200 text-gray-600 font-bold px-5 py-3 rounded-xl text-sm">Ver sitio</a>
      <button type="submit" class="bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-black px-8 py-3 rounded-xl text-sm shadow">
        Guardar Header/Footer
      </button>
    </div>
  </form>

  <!-- Colores y Contador movidos a: Configurar → Configurar Página -->
  <form method="POST" x-show="false" style="display:none!important">
    <input type="hidden" name="tab" value="colores">

    <?php
    // Grupos: cada grupo tiene fondo y texto
    $color_groups = [
        ['label' => 'Colores globales', 'items' => [
            'color_primary' => ['Azul principal (bg)',  'Navbar, fondos de secciones, textos destacados.', '#1E3A8A'],
            'color_accent'  => ['Amarillo acento (bg)', 'Detalles, etiquetas, bordes y highlights.',       '#FACC15'],
        ]],
        ['label' => 'Botón hero principal — "Conoce el Plan"', 'preview' => 'prev-hero-primary', 'items' => [
            'color_btn_hero_primary'      => ['Fondo',  'Color de fondo del botón principal del hero.', '#CC1F2D'],
            'color_btn_hero_primary_text' => ['Texto',  'Color del texto e ícono.',                     '#FFFFFF'],
        ]],
        ['label' => 'Botón hero secundario — "Únete al Equipo"', 'preview' => 'prev-hero-secondary', 'items' => [
            'color_btn_hero_secondary'      => ['Fondo', 'Color de fondo del botón secundario del hero.', '#FACC15'],
            'color_btn_hero_secondary_text' => ['Texto', 'Color del texto e ícono.',                      '#0039A6'],
        ]],
        ['label' => 'Botón descarga / plan — "Ver Plan Completo"', 'preview' => 'prev-download', 'items' => [
            'color_btn_download'      => ['Fondo', '"Ver Plan Completo" y PDF en plan.php.', '#CC1F2D'],
            'color_btn_download_text' => ['Texto', 'Color del texto e ícono.',               '#FFFFFF'],
        ]],
        ['label' => 'Botón CTA navbar — "Únete al equipo"', 'preview' => 'prev-navbar', 'items' => [
            'color_btn_cta_navbar'      => ['Fondo', 'Esquina superior derecha del menú.', '#FACC15'],
            'color_btn_cta_navbar_text' => ['Texto', 'Color del texto.',                   '#1E3A8A'],
        ]],
        ['label' => 'Botón formulario — "Me uno al equipo"', 'preview' => 'prev-join', 'items' => [
            'color_btn_join'      => ['Fondo', 'Enviar del formulario "Únete al equipo".', '#FACC15'],
            'color_btn_join_text' => ['Texto', 'Color del texto.',                         '#0039A6'],
        ]],
    ];
    ?>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="bg-gradient-to-r from-[#1E3A8A] to-[#0056D6] px-6 py-4">
        <h2 class="text-white font-black text-base">Paleta de colores del sitio</h2>
        <p class="text-blue-200 text-xs mt-0.5">Cada botón tiene su propio color de fondo y de texto.</p>
      </div>
      <div class="p-6 space-y-6">
        <?php foreach ($color_groups as $group): ?>
        <div>
          <p class="text-xs font-black text-gray-500 uppercase tracking-widest mb-3"><?= htmlspecialchars($group['label']) ?></p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php foreach ($group['items'] as $key => [$label, $desc, $default]): ?>
            <div class="flex items-start gap-4 p-4 rounded-xl border border-gray-100 bg-gray-50 hover:border-blue-200 transition-colors">
              <div class="flex-shrink-0 mt-0.5">
                <input type="color"
                       name="<?= $key ?>"
                       value="<?= htmlspecialchars(cfg_value($config, $key, $default)) ?>"
                       class="w-12 h-12 rounded-xl border border-gray-200 cursor-pointer p-0.5 bg-white shadow-sm"
                       title="<?= htmlspecialchars($label) ?>">
              </div>
              <div class="min-w-0">
                <p class="text-sm font-black text-gray-800 leading-tight"><?= htmlspecialchars($label) ?></p>
                <p class="text-xs text-gray-400 mt-0.5"><?= htmlspecialchars($desc) ?></p>
                <code class="text-[10px] text-gray-400 font-mono"><?= htmlspecialchars($key) ?></code>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php if (!empty($group['preview'])): ?>
          <div class="mt-3 flex items-center gap-3">
            <span class="text-xs text-gray-400">Preview:</span>
            <span id="<?= $group['preview'] ?>" class="inline-flex items-center gap-2 text-sm font-black px-6 py-2.5 rounded-full shadow cursor-default transition-all">
              <?php
                $previewLabels = [
                  'prev-hero-primary'   => 'Conoce el Plan',
                  'prev-hero-secondary' => 'Únete al Equipo',
                  'prev-download'       => 'Ver Plan 2026-2030',
                  'prev-navbar'         => 'Únete al equipo',
                  'prev-join'           => 'Me uno al equipo',
                ];
                echo htmlspecialchars($previewLabels[$group['preview']] ?? 'Botón');
              ?>
            </span>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="sticky bottom-0 bg-[#F1F5F9]/95 backdrop-blur py-4 flex justify-end gap-3">
      <a href="<?= BASE_URL ?>/index.php" target="_blank" class="bg-white border border-gray-200 text-gray-600 font-bold px-5 py-3 rounded-xl text-sm">Ver sitio</a>
      <button type="submit" class="bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-black px-8 py-3 rounded-xl text-sm shadow">
        Guardar Colores
      </button>
    </div>
  </form>
  <!-- ── /TAB COLORES ──────────────────────────────────────────── -->

  <!-- ── TAB CONTADOR ──────────────────────────────────────────── -->
  <form method="POST" x-show="activeTab === 'contador'" class="space-y-6">
    <input type="hidden" name="tab" value="contador">

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="bg-gradient-to-r from-[#1E3A8A] to-[#0056D6] px-6 py-4">
        <h2 class="text-white font-black text-base">Contador regresivo</h2>
        <p class="text-blue-200 text-xs mt-0.5">Se muestra en el hero del landing page debajo de los botones CTA.</p>
      </div>
      <div class="p-6 space-y-6">

        <!-- Activar / desactivar -->
        <div class="flex items-center justify-between p-4 rounded-xl border border-gray-100 bg-gray-50">
          <div>
            <p class="text-sm font-black text-gray-800">Mostrar contador en el sitio</p>
            <p class="text-xs text-gray-400 mt-0.5">Si está desactivado el bloque no aparece en la web.</p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" name="countdown_active" value="1" class="sr-only peer"
                   <?= cfg_value($config, 'countdown_active', '1') === '1' ? 'checked' : '' ?>>
            <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer
                        peer-checked:bg-[#1E3A8A] transition-colors"></div>
            <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform
                        peer-checked:translate-x-5"></div>
          </label>
        </div>

        <!-- Fecha objetivo -->
        <div class="p-4 rounded-xl border border-gray-100 bg-gray-50">
          <label class="block text-sm font-black text-gray-800 mb-1">Fecha de las elecciones</label>
          <p class="text-xs text-gray-400 mb-3">El contador llega a cero a la medianoche de este día (hora de Lima, UTC-5).</p>
          <input type="date"
                 name="countdown_date"
                 value="<?= htmlspecialchars(cfg_value($config, 'countdown_date', '2026-10-04')) ?>"
                 class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-mono text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-300">
        </div>

        <!-- Título del contador -->
        <div class="p-4 rounded-xl border border-gray-100 bg-gray-50">
          <label class="block text-sm font-black text-gray-800 mb-1">Título (encima de los dígitos)</label>
          <input type="text"
                 name="countdown_title"
                 value="<?= htmlspecialchars(cfg_value($config, 'countdown_title', 'Faltan para las Elecciones')) ?>"
                 maxlength="80"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-300"
                 placeholder="Faltan para las Elecciones">
        </div>

        <!-- Etiqueta inferior -->
        <div class="p-4 rounded-xl border border-gray-100 bg-gray-50">
          <label class="block text-sm font-black text-gray-800 mb-1">Etiqueta inferior (debajo de los dígitos)</label>
          <input type="text"
                 name="countdown_label"
                 value="<?= htmlspecialchars(cfg_value($config, 'countdown_label', 'Elecciones Municipales 2026')) ?>"
                 maxlength="80"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-300"
                 placeholder="Elecciones Municipales 2026">
        </div>

        <!-- Preview estático -->
        <div class="p-4 rounded-xl border border-blue-100 bg-[#0B1E4A]">
          <p class="text-white/40 text-xs uppercase tracking-widest mb-2 font-semibold">Preview (estático)</p>
          <div class="inline-flex items-center gap-2">
            <?php foreach ([['365','Días'],['08','Hrs'],['22','Min'],['10','Seg']] as [$n, $u]): ?>
            <div class="flex flex-col items-center">
              <div class="bg-[#0B1E4A]/80 border border-white/10 rounded-xl px-3 py-2 min-w-[3.2rem] text-center shadow-lg">
                <span class="block text-2xl font-black text-white tabular-nums"><?= $n ?></span>
              </div>
              <span class="text-white/40 text-[10px] uppercase tracking-widest mt-1"><?= $u ?></span>
            </div>
            <?php if ($u !== 'Seg'): ?><span class="text-white/30 text-2xl font-black mb-4">:</span><?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>

      </div>
    </div>

    <!-- ── SECCION: CONTADOR DE VISITAS ── -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-4 flex items-center justify-between">
        <div>
          <h2 class="text-white font-black text-base">Contador de visitas</h2>
          <p class="text-emerald-100 text-xs mt-0.5">Visitantes únicos por IP, una vez por día.</p>
        </div>
        <svg class="w-8 h-8 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        </svg>
      </div>
      <div class="p-6 space-y-6">

        <!-- Activar / desactivar -->
        <div class="flex items-center justify-between p-4 rounded-xl border border-gray-100 bg-gray-50">
          <div>
            <p class="text-sm font-black text-gray-800">Activar registro de visitas</p>
            <p class="text-xs text-gray-400 mt-0.5">Muestra el total en el footer del sitio.</p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" name="visit_counter_active" value="1" class="sr-only peer"
                   <?= cfg_value($config, 'visit_counter_active', '1') === '1' ? 'checked' : '' ?>>
            <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-emerald-300 rounded-full peer
                        peer-checked:bg-emerald-600 transition-colors"></div>
            <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform
                        peer-checked:translate-x-5"></div>
          </label>
        </div>

        <!-- Estadísticas -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <?php foreach ([
            ['Total histórico', $visit_stats['total'],  'text-emerald-600', 'bg-emerald-50'],
            ['Hoy',            $visit_stats['hoy'],    'text-blue-600',    'bg-blue-50'],
            ['Esta semana',    $visit_stats['semana'], 'text-violet-600',  'bg-violet-50'],
            ['Este mes',       $visit_stats['mes'],    'text-orange-500',  'bg-orange-50'],
          ] as [$lbl, $val, $color, $bg]): ?>
          <div class="<?= $bg ?> rounded-2xl p-4 text-center border border-white">
            <p class="<?= $color ?> text-3xl font-black"><?= number_format($val) ?></p>
            <p class="text-gray-500 text-xs uppercase tracking-widest mt-1"><?= $lbl ?></p>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Resetear contador -->
        <div class="p-4 rounded-xl border border-red-100 bg-red-50 flex items-center justify-between gap-4">
          <div>
            <p class="text-sm font-black text-red-700">Reiniciar contador</p>
            <p class="text-xs text-red-400 mt-0.5">Elimina todos los registros de visitas. Esta acción no se puede deshacer.</p>
          </div>
          <button type="submit" name="reset_visits" value="1"
                  onclick="return confirm('¿Seguro que deseas reiniciar el contador a cero? Esta acción no se puede deshacer.')"
                  class="flex-shrink-0 bg-red-600 hover:bg-red-700 text-white font-black text-xs px-4 py-2.5 rounded-xl shadow transition-colors">
            Reiniciar a 0
          </button>
        </div>

      </div>
    </div>
    <!-- ── /CONTADOR DE VISITAS ── -->

    <div class="sticky bottom-0 bg-[#F1F5F9]/95 backdrop-blur py-4 flex justify-end gap-3">
      <a href="<?= BASE_URL ?>/index.php" target="_blank" class="bg-white border border-gray-200 text-gray-600 font-bold px-5 py-3 rounded-xl text-sm">Ver sitio</a>
      <button type="submit" class="bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-black px-8 py-3 rounded-xl text-sm shadow">
        Guardar Contador
      </button>
    </div>
  </form>
  <!-- ── /TAB CONTADOR ─────────────────────────────────────────── -->

</div>

<script>
  (function () {

  // workAxesManager movido a config-plan.php
  function workAxesManager() {
    const presets = {
      campo: { grad: 'from-green-500 to-emerald-600', nav_color: 'bg-green-100 text-green-700', nav_border: 'border-green-300', section_bg: '#F0FDF4', section_border: '#BBF7D0' },
      salud: { grad: 'from-rose-500 to-pink-600', nav_color: 'bg-rose-100 text-rose-700', nav_border: 'border-rose-300', section_bg: '#FFF1F2', section_border: '#FECDD3' },
      agua: { grad: 'from-sky-500 to-cyan-600', nav_color: 'bg-sky-100 text-sky-700', nav_border: 'border-sky-300', section_bg: '#F0F9FF', section_border: '#BAE6FD' },
      obra: { grad: 'from-orange-500 to-amber-600', nav_color: 'bg-orange-100 text-orange-700', nav_border: 'border-orange-300', section_bg: '#FFF7ED', section_border: '#FED7AA' },
      educacion: { grad: 'from-blue-500 to-indigo-600', nav_color: 'bg-blue-100 text-blue-700', nav_border: 'border-blue-300', section_bg: '#EFF6FF', section_border: '#BFDBFE' },
      seguridad: { grad: 'from-red-500 to-rose-600', nav_color: 'bg-red-100 text-red-700', nav_border: 'border-red-300', section_bg: '#FEF2F2', section_border: '#FECACA' },
      comunidad: { grad: 'from-emerald-500 to-teal-600', nav_color: 'bg-emerald-100 text-emerald-700', nav_border: 'border-emerald-300', section_bg: '#ECFDF5', section_border: '#A7F3D0' },
      transporte: { grad: 'from-slate-500 to-blue-600', nav_color: 'bg-slate-100 text-slate-700', nav_border: 'border-slate-300', section_bg: '#F8FAFC', section_border: '#CBD5E1' },
      economia: { grad: 'from-yellow-500 to-amber-600', nav_color: 'bg-yellow-100 text-yellow-700', nav_border: 'border-yellow-300', section_bg: '#FEFCE8', section_border: '#FDE68A' },
      turismo: { grad: 'from-fuchsia-500 to-purple-600', nav_color: 'bg-fuchsia-100 text-fuchsia-700', nav_border: 'border-fuchsia-300', section_bg: '#FDF4FF', section_border: '#F5D0FE' },
      deporte: { grad: 'from-violet-500 to-indigo-600', nav_color: 'bg-violet-100 text-violet-700', nav_border: 'border-violet-300', section_bg: '#F5F3FF', section_border: '#DDD6FE' },
      ambiente: { grad: 'from-lime-500 to-green-600', nav_color: 'bg-lime-100 text-lime-700', nav_border: 'border-lime-300', section_bg: '#F7FEE7', section_border: '#D9F99D' },
      juventud: { grad: 'from-cyan-500 to-blue-600', nav_color: 'bg-cyan-100 text-cyan-700', nav_border: 'border-cyan-300', section_bg: '#ECFEFF', section_border: '#A5F3FC' },
      gestion: { grad: 'from-blue-500 to-indigo-600', nav_color: 'bg-blue-100 text-blue-700', nav_border: 'border-blue-300', section_bg: '#EFF6FF', section_border: '#BFDBFE' }
    };

    const rules = [
      ['campo', ['agricultura', 'agro', 'agrario', 'campo', 'cafe', 'cacao', 'productor', 'ganader']],
      ['salud', ['salud', 'posta', 'hospital', 'medic', 'sanitario']],
      ['agua', ['agua', 'saneamiento', 'desague', 'alcantarillado', 'potable']],
      ['obra', ['infraestructura', 'carretera', 'vial', 'vias', 'puente', 'obra', 'pista', 'camino']],
      ['educacion', ['educacion', 'colegio', 'escuela', 'beca', 'aula', 'docente']],
      ['seguridad', ['seguridad', 'serenazgo', 'policia', 'pnp', 'vigilancia']],
      ['comunidad', ['comunidad', 'comunidades', 'nativa', 'nativo', 'pueblo', 'intercultural']],
      ['transporte', ['transporte', 'movilidad', 'terminal', 'transito']],
      ['economia', ['economia', 'empleo', 'trabajo', 'mercado', 'emprend', 'comercio']],
      ['turismo', ['turismo', 'turistico', 'cultura', 'cultural']],
      ['deporte', ['deporte', 'recreacion', 'joven', 'juventud']],
      ['ambiente', ['ambiente', 'forestal', 'bosque', 'ecologia', 'residuo', 'limpieza']]
    ];

    return {
      axes: Array.isArray(window.__configWorkAxes) ? JSON.parse(JSON.stringify(window.__configWorkAxes)) : [],
      addAxis() {
        const axis = {
          id: '',
          icon: 'gestion',
          label: 'Nuevo eje',
          title: 'Nuevo eje',
          desc: '',
          grad: presets.gestion.grad,
          nav_color: presets.gestion.nav_color,
          nav_border: presets.gestion.nav_border,
          section_bg: presets.gestion.section_bg,
          section_border: presets.gestion.section_border,
          img: '',
          plan_desc: '',
          proposals: ''
        };
        this.axes.push(axis);
      },
      detectIcon(axis) {
        const text = `${axis.title || ''} ${axis.label || ''}`.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        for (const [icon, words] of rules) {
          if (words.some((word) => text.includes(word))) return icon;
        }
        return axis.icon || 'gestion';
      },
      applyPreset(axis) {
        const icon = this.detectIcon(axis);
        axis.icon = icon;
        Object.assign(axis, presets[icon] || presets.gestion);
        if (!axis.id && axis.title) {
          axis.id = axis.title.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        }
      }
    };
  }
</script>

<?php include __DIR__ . '/_media-picker.php'; ?>

    </main>
  </div>
</body>
</html>

