<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'type',
        'question_text',
        'option_a',
        'option_b',
        'option_c',
        'option_d',
        'correct_option',
        'explanation',
        'pair_data',
        'sequence_data',
    ];

    protected $casts = [
        'pair_data' => 'array',
        'sequence_data' => 'array',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }
}
