<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\AdminResetPasswordNotification;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'type', 'role', 'company_id', 'department_id', 'avatar', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function isCompanyAccount(): bool
    {
        return $this->type === 'company';
    }

    public function isPersonalAccount(): bool
    {
        return $this->type === 'personal';
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmail);
    }

    public function sendPasswordResetNotification($token)
    {
        if (in_array($this->role, [
            UserRole::ADMIN,
            UserRole::MANAGER,
            UserRole::STAFF,
            UserRole::ADMIN_MANAGER,
        ])) {
            $this->notify(new AdminResetPasswordNotification($token));

            return;
        }

        $this->notify(new ResetPasswordNotification($token));
    }

        public function toolSubscriptions()
    {
        return $this->hasMany(ToolSubscription::class);
    }

    public function activeToolSubscription(ToolCategory $category): ?ToolSubscription
    {
        return $this->toolSubscriptions()
            ->where('tool_category_id', $category->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->first();
    }

    public function hasActiveToolSubscription(?ToolCategory $category): bool
    {
        if (! $category) {
            return false;
        }

        return $this->activeToolSubscription($category) !== null;
    }

    public function maxFileUploadFor(?ToolCategory $category): int
    {
        if (! $category) {
            return 30;
        }

        $subscription = $this->activeToolSubscription($category);

        if ($subscription && $subscription->plan) {
            return $subscription->plan->max_file_upload;
        }

        return $category->free_max_file_upload ?? 30;
    }
}
