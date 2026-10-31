<?php

namespace Database\Seeders;

use App\Models\ActivityCategory;
use Illuminate\Database\Seeder;

class ActivityCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Correr', 'slug' => 'running', 'description' => 'Carreras y entrenamiento a pie al aire libre.'],
            ['name' => 'Ciclismo', 'slug' => 'cycling', 'description' => 'Rutas y entrenamiento en bicicleta.'],
            ['name' => 'Picnic', 'slug' => 'picnic', 'description' => 'Reuniones y comidas al aire libre.'],
            ['name' => 'Senderismo', 'slug' => 'hiking', 'description' => 'Excursiones y caminatas en senderos.'],
            ['name' => 'Playa', 'slug' => 'beach', 'description' => 'Actividades en la playa y zonas costeras.'],
            ['name' => 'Viaje', 'slug' => 'travel', 'description' => 'Desplazamientos y viajes.'],
            ['name' => 'Fotografía exterior', 'slug' => 'outdoor_photography', 'description' => 'Sesiones fotográficas al aire libre.'],
            ['name' => 'Otras actividades', 'slug' => 'other', 'description' => 'Actividades no clasificadas.'],
        ];

        foreach ($categories as $category) {
            ActivityCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category + ['active' => true],
            );
        }
    }
}
