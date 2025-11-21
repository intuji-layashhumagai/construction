<?php

namespace App\Actions\Worker;

use App\Models\Worker;

final class GetSingleWorkerAction
{
    public static function handle(string $email, ?string $status = null): ?Worker
    {

        $query = Worker::where('email', $email);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->first();

    }
}
