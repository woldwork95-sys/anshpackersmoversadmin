<?php

/**
 * Small view helpers shared by the admin screens.
 */
function block_html(?string $html): string
{
    return service('blockHtml')->render($html);
}

function admin_empty(string $message): string
{
    return '<div class="empty" role="status"><p>' . esc($message) . '</p></div>';
}

function admin_old(string $key, string $default = ''): string
{
    $value = old($key, null, false);

    return is_string($value) ? $value : $default;
}

function admin_datetime(mixed $value): string
{
    if (! is_string($value) || $value === '') {
        return '—';
    }

    $time = strtotime($value);

    return $time === false ? '—' : date('M j, Y g:i A', $time);
}

function admin_badge(string $status): string
{
    $known = ['published', 'draft', 'active', 'inactive'];
    $class = in_array($status, $known, true) ? $status : 'unknown';
    $label = $status === '' ? 'Unknown' : $status;

    return '<span class="badge badge-' . esc($class, 'attr') . '">' . esc($label) . '</span>';
}
