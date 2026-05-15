<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectType extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'label'];

    public static function findByKeyOrLabel(string $value): ?self
    {
        $value = trim(strtolower($value));

        return self::query()
            ->whereRaw('lower(key) = ?', [$value])
            ->orWhereRaw('lower(label) = ?', [$value])
            ->first();
    }
}
