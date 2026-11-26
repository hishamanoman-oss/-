<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Models;

use App\Domain\Identity\Models\TenantMembership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserBranchAccess extends Model
{
    protected $table = 'user_branch_access';

    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = [
        'tenant_membership_id',
        'branch_id',
        'access_level',
    ];

    public function membership(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'tenant_membership_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
