<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RatingQuestionsSeeder extends Seeder
{
    /**
     * Master rating questions synced with Flutter app source.
     *
     * Question Codes:
     * 10 → 210
     *
     * IMPORTANT:
     * This seeder must stay synchronized with:
     * - Flutter ratingQuestions list
     * - API validation
     * - Firestore migration logic
     * - Ratings submission flow
     */
    public function run(): void
    {
        $questions = [
            [
                'question_code' => 10,
                'title_en' => 'Is Professional',
                'title_es' => 'Es Profesional',
                'title_pt' => 'É Profissional',
            ],
            [
                'question_code' => 20,
                'title_en' => 'Actively Listens',
                'title_es' => 'Escucha Activamente',
                'title_pt' => 'Ouve Ativamente',
            ],
            [
                'question_code' => 30,
                'title_en' => 'Effectively Communicates',
                'title_es' => 'Se Comunica Eficazmente',
                'title_pt' => 'Comunica-se de Forma Eficaz',
            ],
            [
                'question_code' => 40,
                'title_en' => 'Is Responsive',
                'title_es' => 'Es Receptivo',
                'title_pt' => 'É Responsivo',
            ],
            [
                'question_code' => 50,
                'title_en' => 'Prepares for our Meetings',
                'title_es' => 'Respeta Mi Tiempo',
                'title_pt' => 'Respeita o Meu Tempo',
            ],
            [
                'question_code' => 60,
                'title_en' => 'Provides creative solutions',
                'title_es' => 'Proporciona soluciones creativas',
                'title_pt' => 'Oferece soluções criativas',
            ],
            [
                'question_code' => 70,
                'title_en' => 'Is Knowledgeable',
                'title_es' => 'Es Conocedor',
                'title_pt' => 'É Conhecedor',
            ],
            [
                'question_code' => 80,
                'title_en' => 'Demonstrates Accountability',
                'title_es' => 'Es Responsable',
                'title_pt' => 'É Responsável',
            ],
            [
                'question_code' => 90,
                'title_en' => 'Delivers on Commitments',
                'title_es' => 'Cumple Con Los Plazos',
                'title_pt' => 'Cumpre Prazos',
            ],
            [
                'question_code' => 100,
                'title_en' => 'Is Committed to Safety',
                'title_es' => 'Cumple con los Procedimientos de Seguridad',
                'title_pt' => 'Cumpre os Procedimentos de Segurança',
            ],
            [
                'question_code' => 110,
                'title_en' => 'Demonstrates Technical Ability',
                'title_es' => 'Demuestra Capacidad Técnica',
                'title_pt' => 'Demonstra capacidade técnica',
            ],
            [
                'question_code' => 120,
                'title_en' => 'Understands Client Requirements',
                'title_es' => 'Comprende los Requisitos del Cliente',
                'title_pt' => 'Compreende os requisitos do cliente',
            ],
            [
                'question_code' => 130,
                'title_en' => 'Is able to Interpret Specifications',
                'title_es' => 'Es Capaz de Interpretar las Especificaciones',
                'title_pt' => 'É Capaz de Interpretar as Especificações',
            ],
            [
                'question_code' => 140,
                'title_en' => 'Is Knowledgeable of Industry Practices & Codes',
                'title_es' => 'Posee Conocimiento de las Prácticas y Norma',
                'title_pt' => 'Possui Conhecimento das Práticas e Normas do Setor',
            ],
            [
                'question_code' => 150,
                'title_en' => 'Respects My Time',
                'title_es' => 'Respeta Mi Tiempo',
                'title_pt' => 'Respeita o Meu Tempo',
            ],
            [
                'question_code' => 160,
                'title_en' => 'Offers Relevant Solutions',
                'title_es' => 'Ofrece Soluciones Relevantes',
                'title_pt' => 'Oferece Soluções Relevantes',
            ],
            [
                'question_code' => 170,
                'title_en' => 'Meets Deadlines',
                'title_es' => 'Cumple Con Los Plazos',
                'title_pt' => 'Cumpre Prazos',
            ],
            [
                'question_code' => 180,
                'title_en' => 'Reaches out Proactively',
                'title_es' => 'Se Comunica de Manera Proactiva',
                'title_pt' => 'Entra em Contato de Forma Proativa',
            ],
            [
                'question_code' => 190,
                'title_en' => 'Adheres to Protocols and Safety Procedures',
                'title_es' => 'Cumple con los Procedimientos de Seguridad',
                'title_pt' => 'Cumpre os Procedimentos de Segurança',
            ],
            [
                'question_code' => 200,
                'title_en' => 'Provides tools/metrics to Measure Success',
                'title_es' => 'Proporciona Métricas para Medir el Exito',
                'title_pt' => 'Fornece Métricas para Medir o Sucesso',
            ],
            [
                'question_code' => 210,
                'title_en' => 'Is Accountable',
                'title_es' => 'Es Responsable',
                'title_pt' => 'É Responsável',
            ],
        ];

        foreach ($questions as $question) {
            DB::table('rating_questions')->updateOrInsert(
                [
                    'question_code' => $question['question_code'],
                ],
                array_merge($question, [
                    'is_active' => true,
                    'created_by' => null,
                    'updated_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info(
            '✓ Rating questions seeded successfully: '.count($questions).' rows.'
        );
    }
}
