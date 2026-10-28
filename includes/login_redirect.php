<?php
// Accepteer alleen paden binnen deze website; "//host" en "/\host" zouden naar een andere site verwijzen.
function geldigeRedirectNaInloggen(mixed $pad): ?string
{
    if (!is_string($pad) || strlen($pad) > 2000) {
        return null;
    }
    if (!preg_match('#^/(?![/\\\\])[^\r\n]*$#', $pad)) {
        return null;
    }
    // Voorkom een lus terug naar de loginpagina zelf.
    if (preg_match('#^/includes/login\.php(?:[?\#]|$)#i', $pad)) {
        return null;
    }
    return $pad;
}
