<?php

namespace NexusPlugin\Blindbox\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use NexusPlugin\Blindbox\Services\BlindboxService;

class ResetDailyBlindboxCommand extends Command
{
    protected $signature = 'blindbox:reset-daily';
    
    protected $description = 'Reset daily blindbox statistics';

    public function handle()
    {
        $this->info('Resetting daily blindbox statistics...');
        
        // 重置每日发放数量
        DB::table('plugin_blindbox_prizes')->update([
            'given_today' => 0
        ]);
        
        $this->info('Daily statistics reset successfully.');
    }
}
