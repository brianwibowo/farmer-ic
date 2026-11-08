<?php

namespace Tests\Feature\Auth;

use App\Models\Team;
use App\Models\TeamInvitation as Invitation;
use App\Models\User;
use App\Notifications\Teams\TeamInvitation;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class IndonesianEmailTest extends TestCase
{
    public function test_reset_and_verification_emails_use_indonesian_without_changing_application_locale(): void
    {
        config(['mail.default' => 'array', 'app.locale' => 'en']);
        app()->setLocale('en');
        $user = new User(['name' => 'Sari', 'email' => 'sari@example.com']);
        $user->forceFill(['id' => '00000000-0000-4000-8000-000000000001']);
        Notification::sendNow($user, new ResetPassword('dummy-token'));
        // Verification routes are disabled in this app; isolate the email content test.
        VerifyEmail::createUrlUsing(fn () => 'https://example.test/verify');
        try {
            Notification::sendNow($user, new VerifyEmail);
        } finally {
            VerifyEmail::$createUrlCallback = null;
        }
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $reset = $messages[0]->getOriginalMessage();
        $verify = $messages[1]->getOriginalMessage();

        $this->assertSame('Atur ulang kata sandi Anda', $reset->getSubject());
        $this->assertStringContainsString('Atur Ulang Kata Sandi', $reset->getHtmlBody());
        $this->assertStringContainsString('berlaku selama 60 menit', $reset->getHtmlBody());
        $this->assertStringContainsString('Jika tombol', $reset->getHtmlBody());
        $this->assertStringContainsString('Hak cipta dilindungi.', $reset->getHtmlBody());
        $this->assertStringContainsString('Atur Ulang Kata Sandi', $reset->getTextBody());
        $this->assertSame('Verifikasi alamat email Anda', $verify->getSubject());
        $this->assertStringContainsString('Verifikasi Alamat Email', $verify->getHtmlBody());
        $this->assertSame('en', app()->getLocale());
    }

    public function test_team_invitation_email_is_in_indonesian(): void
    {
        config(['mail.default' => 'array']);
        $team = new Team(['name' => 'Kebun Sejahtera']);
        $invitation = new Invitation;
        $invitation->forceFill(['code' => 'invitation-code']);
        $invitation->setRelation('team', $team);
        $invitation->setRelation('inviter', new User(['name' => 'Sari']));
        Notification::sendNow(Notification::route('mail', 'recipient@example.com'), new TeamInvitation($invitation));
        $message = Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();

        $this->assertSame('Anda diundang untuk bergabung dengan Kebun Sejahtera', $message->getSubject());
        $this->assertStringContainsString('Sari mengundang Anda', $message->getHtmlBody());
        $this->assertStringContainsString('Hak cipta dilindungi.', $message->getHtmlBody());
    }
}
