<?php

namespace App\Models\Workshop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

class WorkOrderAttachment extends Model
{
    protected $table = 'workshop_work_order_attachments';

    protected $fillable = [
        'work_order_id',
        'user_id',
        'filename',
        'original_filename',
        'file_path',
        'file_type',
        'file_size',
        'mime_type',
        'category',
        'description',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime',
    ];

    // Categorias de anexos
    const CATEGORY_PHOTO_BEFORE = 'photo_before';
    const CATEGORY_PHOTO_AFTER = 'photo_after';
    const CATEGORY_PHOTO_DAMAGE = 'photo_damage';
    const CATEGORY_DOCUMENT = 'document';
    const CATEGORY_INVOICE = 'invoice';
    const CATEGORY_OTHER = 'other';

    // Relationships
    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Accessors
    public function getFileSizeFormattedAttribute()
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }

    public function getFileUrlAttribute()
    {
        return Storage::url($this->file_path);
    }

    public function getIsImageAttribute()
    {
        return in_array($this->mime_type, [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml'
        ]);
    }

    public function getCategoryLabelAttribute()
    {
        return match($this->category) {
            self::CATEGORY_PHOTO_BEFORE => 'Foto Antes',
            self::CATEGORY_PHOTO_AFTER => 'Foto Depois',
            self::CATEGORY_PHOTO_DAMAGE => 'Foto de Dano',
            self::CATEGORY_DOCUMENT => 'Documento',
            self::CATEGORY_INVOICE => 'Fatura',
            self::CATEGORY_OTHER => 'Outro',
            default => 'Arquivo',
        };
    }

    public function getCategoryIconAttribute()
    {
        return match($this->category) {
            self::CATEGORY_PHOTO_BEFORE => 'camera',
            self::CATEGORY_PHOTO_AFTER => 'camera-retro',
            self::CATEGORY_PHOTO_DAMAGE => 'exclamation-triangle',
            self::CATEGORY_DOCUMENT => 'file-alt',
            self::CATEGORY_INVOICE => 'file-invoice',
            self::CATEGORY_OTHER => 'paperclip',
            default => 'file',
        };
    }

    public function getCategoryColorAttribute()
    {
        return match($this->category) {
            self::CATEGORY_PHOTO_BEFORE => 'blue',
            self::CATEGORY_PHOTO_AFTER => 'green',
            self::CATEGORY_PHOTO_DAMAGE => 'red',
            self::CATEGORY_DOCUMENT => 'purple',
            self::CATEGORY_INVOICE => 'yellow',
            self::CATEGORY_OTHER => 'gray',
            default => 'gray',
        };
    }

    // Methods
    public function deleteFile()
    {
        if (Storage::exists($this->file_path)) {
            Storage::delete($this->file_path);
        }
        $this->delete();
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($attachment) {
            // Deletar arquivo ao remover registro
            if (Storage::exists($attachment->file_path)) {
                Storage::delete($attachment->file_path);
            }
        });
    }
}
