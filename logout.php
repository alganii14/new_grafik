<?php

require_once __DIR__ . '/auth.php';

start_secure_session();
send_security_headers("default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . (is_authenticated() ? 'index.php' : 'login.php'));
    exit;
}

$token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!is_authenticated() || !csrf_is_valid($token)) {
    http_response_code(403);
    exit('Permintaan logout tidak valid.');
}

end_authenticated_session('logout');
header('Location: login.php?logged_out=1');
exit;
