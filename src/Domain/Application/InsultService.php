<?php

namespace App\Services;

class InsultService {
    
    private static array $burns = [
        "It's a Hammer, not an Artisan! 🔨",
        "Artisan? Who? We crush bugs with Hammers here.🤡",
        "Wrong project, buddy. The clowns are running this circus. 🎪",
        "Artisan is for architects. Hammers are for demolishers. 🔥",
        "Cute name, 'Artisan'. Does it come with a little beret? 🎨🔨",
        "Beep boop. Muscle memory detected. Initiating Hammer protocol.",
    ];

    /**
     * Get a random cheeky comment.
     */
    public static function getRandom(): string {
        return self::$burns[array_rand(self::$burns)];
    }

    /**
     * Get the specific artisan warning message.
     */
    public static function getArtisanWarning(): string {
        return "\e[33m[NOTICE] " . self::getRandom() . "\e[0m\n";
    }
}