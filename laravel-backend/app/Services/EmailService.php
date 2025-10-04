<?php

namespace App\Services;

use App\Models\User;
use App\Mail\WelcomeEmail;
use App\Services\PDFService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailService
{
    protected $pdfService;

    public function __construct(PDFService $pdfService)
    {
        $this->pdfService = $pdfService;
    }

    /**
     * Send welcome email with QR code to user.
     */
    public function sendWelcomeEmail(User $user): bool
    {
        try {
            // Generate PDF with QR code
            $pdfPath = $this->pdfService->generateUserPDF($user);

            // Send email
            Mail::to($user->email)->send(new WelcomeEmail($user, $pdfPath));

            // Mark email as sent
            $user->markEmailAsSent();

            // Clean up temporary PDF
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }

            Log::info("Welcome email sent to user {$user->id} ({$user->email})");

            return true;

        } catch (\Exception $e) {
            // Mark email as failed
            $user->markEmailAsFailed($e->getMessage());

            Log::error("Failed to send welcome email to user {$user->id}: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Send birthday email to user.
     */
    public function sendBirthdayEmail(User $user): bool
    {
        try {
            // Check if it's user's birthday
            if (!$this->isUserBirthday($user)) {
                return false;
            }

            // Generate birthday PDF
            $pdfPath = $this->pdfService->generateBirthdayPDF($user);

            // Send birthday email
            Mail::to($user->email)->send(new BirthdayEmail($user, $pdfPath));

            // Clean up temporary PDF
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }

            Log::info("Birthday email sent to user {$user->id} ({$user->email})");

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to send birthday email to user {$user->id}: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Send bulk emails to multiple users.
     */
    public function sendBulkEmails(array $userIds, string $subject, string $message): array
    {
        $results = [
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($userIds as $userId) {
            try {
                $user = User::findOrFail($userId);
                
                // Send custom email
                Mail::to($user->email)->send(new CustomEmail($user, $subject, $message));
                
                $results['sent']++;
                
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Check if it's user's birthday.
     */
    private function isUserBirthday(User $user): bool
    {
        $today = now();
        $birthday = $user->birth_date;

        return $today->month === $birthday->month && $today->day === $birthday->day;
    }

    /**
     * Get users with failed emails.
     */
    public function getUsersWithFailedEmails(): \Illuminate\Database\Eloquent\Collection
    {
        return User::where('email_status', 'failed')->get();
    }

    /**
     * Retry failed emails.
     */
    public function retryFailedEmails(): array
    {
        $failedUsers = $this->getUsersWithFailedEmails();
        $results = [
            'retried' => 0,
            'still_failed' => 0,
            'errors' => []
        ];

        foreach ($failedUsers as $user) {
            try {
                if ($this->sendWelcomeEmail($user)) {
                    $results['retried']++;
                } else {
                    $results['still_failed']++;
                }
            } catch (\Exception $e) {
                $results['still_failed']++;
                $results['errors'][] = [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}