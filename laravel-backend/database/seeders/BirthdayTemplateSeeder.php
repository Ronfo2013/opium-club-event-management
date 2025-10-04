<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BirthdayTemplate;

class BirthdayTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Template Elegante',
                'subject' => 'Auguri di Buon Compleanno da Opium Club!',
                'html_content' => $this->getElegantTemplate(),
                'background_image' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Template Moderno',
                'subject' => 'Tanti Auguri per il tuo Compleanno!',
                'html_content' => $this->getModernTemplate(),
                'background_image' => null,
                'is_active' => false,
            ],
            [
                'name' => 'Template Minimalista',
                'subject' => 'Buon Compleanno!',
                'html_content' => $this->getMinimalTemplate(),
                'background_image' => null,
                'is_active' => false,
            ],
        ];

        foreach ($templates as $templateData) {
            BirthdayTemplate::create($templateData);
        }
    }

    private function getElegantTemplate(): string
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Auguri di Compleanno</title>
        </head>
        <body style="font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 0; padding: 20px;">
            <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px; text-align: center;">
                    <h1 style="color: white; margin: 0; font-size: 32px;">🎉 Buon Compleanno! 🎉</h1>
                </div>
                <div style="padding: 40px; text-align: center;">
                    <h2 style="color: #333; margin-bottom: 20px;">Ciao {nome} {cognome}!</h2>
                    <p style="color: #666; font-size: 18px; line-height: 1.6;">
                        Ti auguriamo un fantastico compleanno! Che questo nuovo anno di vita ti porti tanta gioia, successo e momenti indimenticabili.
                    </p>
                    <p style="color: #666; font-size: 16px;">
                        Vieni a festeggiare con noi all\'Opium Club Pordenone!
                    </p>
                    <div style="margin: 30px 0;">
                        <a href="https://www.opiumclubpordenone.com" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px; display: inline-block; font-weight: bold;">
                            Visita il nostro sito
                        </a>
                    </div>
                </div>
                <div style="background: #f8f9fa; padding: 20px; text-align: center; color: #666;">
                    <p>Opium Club Pordenone | Il tuo locale di fiducia per eventi indimenticabili</p>
                </div>
            </div>
        </body>
        </html>';
    }

    private function getModernTemplate(): string
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Auguri di Compleanno</title>
        </head>
        <body style="font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; margin: 0; padding: 20px;">
            <div style="max-width: 500px; margin: 0 auto; background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
                <div style="background: #2c3e50; padding: 30px; text-align: center;">
                    <h1 style="color: white; margin: 0; font-size: 28px;">🎂 Tanti Auguri!</h1>
                </div>
                <div style="padding: 30px; text-align: center;">
                    <h2 style="color: #2c3e50; margin-bottom: 15px;">Ciao {nome}!</h2>
                    <p style="color: #7f8c8d; font-size: 16px; line-height: 1.5;">
                        Buon compleanno! Che questo nuovo anno ti porti tanta felicità e successo.
                    </p>
                </div>
            </div>
        </body>
        </html>';
    }

    private function getMinimalTemplate(): string
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Auguri di Compleanno</title>
        </head>
        <body style="font-family: Arial, sans-serif; background: white; margin: 0; padding: 40px; text-align: center;">
            <h1 style="color: #333; font-size: 24px;">Buon Compleanno {nome}!</h1>
            <p style="color: #666; font-size: 16px;">Tanti auguri per il tuo speciale giorno!</p>
        </body>
        </html>';
    }
}






