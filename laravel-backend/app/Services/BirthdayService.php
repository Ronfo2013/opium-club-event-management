<?php

namespace App\Services;

use App\Models\User;
use App\Models\BirthdayTemplate;
use App\Models\BirthdaySent;
use App\Services\EmailService;
use Illuminate\Support\Facades\Log;

class BirthdayService
{
    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Send birthday emails for users with birthdays today.
     */
    public function sendBirthdayEmails(): array
    {
        $results = [
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        // Get active template
        $template = BirthdayTemplate::getActive();
        if (!$template) {
            $results['errors'][] = 'No active birthday template found';
            return $results;
        }

        // Get users with birthdays today
        $today = now()->format('m-d');
        $currentYear = now()->year;

        $users = User::whereRaw("DATE_FORMAT(birth_date, '%m-%d') = ?", [$today])
            ->get();

        foreach ($users as $user) {
            try {
                // Check if birthday was already sent this year
                if (BirthdaySent::wasSentForUser($user->email, $currentYear)) {
                    $results['skipped']++;
                    continue;
                }

                // Send birthday email
                if ($this->emailService->sendBirthdayEmail($user, $template->html_content, $template->subject)) {
                    // Mark as sent
                    BirthdaySent::markAsSent(
                        $user->email,
                        $user->full_name,
                        $user->birth_date->format('Y-m-d'),
                        $currentYear,
                        $template->id
                    );

                    $results['sent']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to send birthday email to {$user->email}";
                }

            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Error for {$user->email}: " . $e->getMessage();
                
                Log::error('Birthday email error', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Birthday emails processed', $results);

        return $results;
    }

    /**
     * Send birthday email for a specific user.
     */
    public function sendBirthdayEmailForUser(User $user): bool
    {
        $template = BirthdayTemplate::getActive();
        if (!$template) {
            return false;
        }

        $currentYear = now()->year;

        // Check if birthday was already sent this year
        if (BirthdaySent::wasSentForUser($user->email, $currentYear)) {
            return false;
        }

        try {
            // Send birthday email
            if ($this->emailService->sendBirthdayEmail($user, $template->html_content, $template->subject)) {
                // Mark as sent
                BirthdaySent::markAsSent(
                    $user->email,
                    $user->full_name,
                    $user->birth_date->format('Y-m-d'),
                    $currentYear,
                    $template->id
                );

                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Birthday email error for user', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get users with upcoming birthdays.
     */
    public function getUpcomingBirthdays(int $days = 7): array
    {
        $upcomingDates = [];
        $currentDate = now();

        for ($i = 0; $i < $days; $i++) {
            $date = $currentDate->copy()->addDays($i);
            $monthDay = $date->format('m-d');

            $users = User::whereRaw("DATE_FORMAT(birth_date, '%m-%d') = ?", [$monthDay])
                ->get()
                ->map(function ($user) use ($date) {
                    return [
                        'user' => $user,
                        'birthday_date' => $date->format('Y-m-d'),
                        'age' => $user->birth_date->copy()->setYear($date->year)->age,
                    ];
                });

            if ($users->isNotEmpty()) {
                $upcomingDates[] = [
                    'date' => $date->format('Y-m-d'),
                    'formatted_date' => $date->format('d/m/Y'),
                    'users' => $users,
                ];
            }
        }

        return $upcomingDates;
    }

    /**
     * Get birthday statistics.
     */
    public function getBirthdayStats(): array
    {
        $currentYear = now()->year;
        $currentMonth = now()->month;

        return [
            'total_birthdays_this_year' => BirthdaySent::where('sent_year', $currentYear)->count(),
            'birthdays_this_month' => BirthdaySent::where('sent_year', $currentYear)
                ->whereMonth('sent_at', $currentMonth)
                ->count(),
            'birthdays_today' => BirthdaySent::where('sent_year', $currentYear)
                ->whereDate('sent_at', now()->toDateString())
                ->count(),
            'upcoming_birthdays' => $this->getUpcomingBirthdays(7),
        ];
    }

    /**
     * Create default birthday templates.
     */
    public function createDefaultTemplates(): void
    {
        $templates = [
            [
                'name' => 'Template Elegante',
                'subject' => 'Auguri di Buon Compleanno da Opium Club!',
                'html_content' => $this->getElegantTemplate(),
                'is_active' => true,
            ],
            [
                'name' => 'Template Moderno',
                'subject' => 'Tanti Auguri per il tuo Compleanno!',
                'html_content' => $this->getModernTemplate(),
                'is_active' => false,
            ],
            [
                'name' => 'Template Minimalista',
                'subject' => 'Buon Compleanno!',
                'html_content' => $this->getMinimalTemplate(),
                'is_active' => false,
            ],
        ];

        foreach ($templates as $templateData) {
            BirthdayTemplate::create($templateData);
        }
    }

    /**
     * Get elegant birthday template.
     */
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

    /**
     * Get modern birthday template.
     */
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

    /**
     * Get minimal birthday template.
     */
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






