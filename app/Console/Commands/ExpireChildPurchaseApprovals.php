<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Player\ExpireChildPurchaseApprovals as ExpireApprovals;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('approvals:expire')]
#[Description('Auto-deny child purchase approvals that were not answered in time')]
class ExpireChildPurchaseApprovals extends Command
{
    public function handle(ExpireApprovals $expire): int
    {
        $this->info(__(':count approval(s) expired.', ['count' => $expire->handle()]));

        return self::SUCCESS;
    }
}
