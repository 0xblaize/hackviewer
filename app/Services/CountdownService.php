<?php

declare(strict_types=1);

namespace App\Services;

final class CountdownService
{
    public function label(?string $endAt): string
    {
        if (!$endAt) {
            return 'Deadline not reported';
        }
        $seconds = strtotime($endAt) - time();
        if ($seconds <= 0) {
            return 'Ended';
        }
        if ($seconds < 3600) {
            return ceil($seconds / 60) . 'm left';
        }
        if ($seconds < 86400) {
            return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm left';
        }
        if ($seconds < 2592000) {
            return floor($seconds / 86400) . 'd ' . floor(($seconds % 86400) / 3600) . 'h left';
        }
        return floor($seconds / 2592000) . 'mo ' . floor(($seconds % 2592000) / 86400) . 'd left';
    }

    public function status(?string $endAt): string
    {
        if (!$endAt || strtotime($endAt) <= time()) {
            return 'ended';
        }
        return strtotime($endAt) - time() <= 604800 ? 'ending-soon' : 'open';
    }
}
