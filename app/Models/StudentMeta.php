<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentMeta extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'student_meta';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'meta_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'student_id',
        'meta_key',
        'meta_value',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'meta_value' => 'array', // سيتم تحويل JSON تلقائياً
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Get the student that owns the meta.
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    /**
     * Set the meta_value attribute.
     *
     * @param  mixed  $value
     * @return void
     */
    public function setMetaValueAttribute($value)
    {
        $this->attributes['meta_value'] = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * Get the meta_value attribute.
     *
     * @param  string  $value
     * @return mixed
     */
    public function getMetaValueAttribute($value)
    {
        // حاول تحويل JSON إلى array
        $decoded = json_decode($value, true);

        // إذا كان JSON صالح، أرجع الـ array
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // وإلا أرجع القيمة كما هي
        return $value;
    }

    /**
     * Scope a query to get meta by key.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $key
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForKey($query, $key)
    {
        return $query->where('meta_key', $key);
    }

    /**
     * Get meta value for a specific key.
     *
     * @param  int  $studentId
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public static function getValue($studentId, $key, $default = null)
    {
        $meta = self::where('student_id', $studentId)
            ->where('meta_key', $key)
            ->first();

        return $meta ? $meta->meta_value : $default;
    }

    /**
     * Set meta value for a specific key.
     *
     * @param  int  $studentId
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public static function setValue($studentId, $key, $value)
    {
        $meta = self::updateOrCreate(
            [
                'student_id' => $studentId,
                'meta_key' => $key,
            ],
            [
                'meta_value' => $value,
            ]
        );

        return $meta;
    }

    /**
     * Delete meta for a specific key.
     *
     * @param  int  $studentId
     * @param  string  $key
     * @return bool
     */
    public static function deleteValue($studentId, $key)
    {
        return self::where('student_id', $studentId)
            ->where('meta_key', $key)
            ->delete();
    }
}
