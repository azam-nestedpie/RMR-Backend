<?php

namespace App\Services\V1;

use App\Models\User;

class LanguageService
{
    public function updateLanguage(User $user, string $locale): User
    {
        $user->update(['prefered_locale' => $locale]);

        return $user->fresh();
    }
}
