<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TodoComment extends Model
{
<<<<<<< HEAD
    protected $fillable = ['todo_id', 'user_id', 'content'];

    public function todo()
    {
        return $this->belongsTo(Todo::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
=======
    //
}
>>>>>>> d365469 (Update Outgoing call telepon group (belum listener) + fix todo list)
