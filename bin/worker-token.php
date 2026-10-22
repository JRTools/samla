<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
// Explicit local provisioning tool. It neither writes configuration nor sends a token.
$token=bin2hex(random_bytes(32));
fwrite(STDOUT,"Worker token (keep private): $token\nAPP_WORKER_HTTP_TOKEN_SHA256=".hash('sha256',$token)."\n");
