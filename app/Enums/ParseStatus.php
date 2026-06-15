<?php

namespace App\Enums;

enum ParseStatus: string
{
    case Pending = 'pending';
    case Ok = 'ok';
    case Unreachable = 'unreachable';
    case MarkupChanged = 'markup_changed';
    case Empty = 'empty';
    case Captcha = 'captcha';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'ожидает парсинга',
            self::Ok => 'готово',
            self::Unreachable => 'страница недоступна',
            self::MarkupChanged => 'разметка изменилась',
            self::Empty => 'пустой ответ',
            self::Captcha => 'Яндекс запросил капчу',
        };
    }
}
