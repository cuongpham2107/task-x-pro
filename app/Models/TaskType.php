<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class TaskType extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'label'];

    public static function keys(): array
    {
        if (! Schema::hasTable((new self)->getTable())) {
            return [];
        }

        return self::query()->orderBy('label')->pluck('key')->values()->all();
    }

    public static function labels(): array
    {
        if (! Schema::hasTable((new self)->getTable())) {
            return [];
        }

        return self::query()->orderBy('label')->pluck('label', 'key')->toArray();
    }

    public static function findByKeyOrLabel(string $value): ?self
    {
        $value = trim(mb_strtolower($value));

        if ($value === '') {
            return null;
        }

        if (! Schema::hasTable((new self)->getTable())) {
            return null;
        }

        return self::query()
            ->whereRaw('LOWER(key) = ?', [$value])
            ->orWhereRaw('LOWER(label) = ?', [$value])
            ->first();
    }
}
