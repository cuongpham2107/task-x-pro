<?php

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\KpiScore;
use App\Models\User;
use Illuminate\Console\Command;

class KpiBackfillMissingMonths extends Command
{
    protected $signature = 'kpi:backfill-missing-months {--months=12 : Número de meses anteriores a preencher}';

    protected $description = 'Preenche períodos de KPI em falta para usuários sem tarefas completadas';

    public function handle(): void
    {
        $monthsBack = (int) $this->option('months');
        $now = now();
        $startDate = $now->copy()->subMonths($monthsBack)->startOfMonth();

        // Gerar todos os períodos (ano-mês) entre startDate e agora
        $allPeriods = collect();
        $current = $startDate->copy();
        while ($current->lte($now)) {
            $allPeriods->push([
                'type' => 'monthly',
                'year' => $current->year,
                'value' => $current->month,
            ]);
            $current->addMonth();
        }

        $this->info("Preenchendo KPI para {$allPeriods->count()} períodos...");

        $users = User::where('status', UserStatus::Active->value)->get(['id']);
        $totalUsers = $users->count();
        $createdCount = 0;

        $progressBar = $this->output->createProgressBar($totalUsers);
        $progressBar->start();

        foreach ($users as $user) {
            foreach ($allPeriods as $period) {
                $exists = KpiScore::where('user_id', $user->id)
                    ->where('period_type', $period['type'])
                    ->where('period_year', $period['year'])
                    ->where('period_value', $period['value'])
                    ->exists();

                if (! $exists) {
                    $kpiScore = KpiScore::create([
                        'user_id' => $user->id,
                        'period_type' => $period['type'],
                        'period_year' => $period['year'],
                        'period_value' => $period['value'],
                        'period_id' => "{$period['type']}-{$period['year']}-{$period['value']}",
                        'total_tasks' => 0,
                        'on_time_tasks' => 0,
                        'on_time_rate' => 0.00,
                        'sla_met_tasks' => 0,
                        'sla_rate' => 0.00,
                        'avg_star' => 0.00,
                        'target_score' => 100,
                        'actual_score' => 0.00,
                        'status' => 'pending',
                        'final_score' => 0.00,
                        'calculated_at' => now(),
                    ]);
                    $createdCount++;

                    // Recalcular com dados reais se houver tarefas
                    $kpiScore->recalculateFromSourceData();
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);
        $this->info("✅ Preenchimento concluído! {$createdCount} registros KPI criados.");
    }
}
