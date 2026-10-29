<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    require_once dirname(__DIR__, 2) . '/admin/bootstrap_pgs.php';

    $opts = getopt('', ['status', 'run::', 'all', 'env::']);
    if (isset($opts['env']) && is_string($opts['env'])) {
        putenv('APP_ENV=' . $opts['env']);
        $_ENV['APP_ENV'] = $opts['env'];
        \Pgs\Core\Config::reset();
        \Pgs\Core\Db::reset();
    }

    $migrator = new \Pgs\Migrations\Migrator();

    if (isset($opts['status'])) {
        echo "=== Migration Status ===" . PHP_EOL;
        foreach ($migrator->status() as $m) {
            printf(
                "[%-16s] %-35s %s (%s ms)\n",
                $m['status'],
                $m['id'],
                $m['description'],
                $m['duration_ms'] ?? '-'
            );
        }
        exit(0);
    }

    if (isset($opts['run']) && is_string($opts['run'])) {
        $id = $opts['run'];
        echo "Running migration: $id" . PHP_EOL;
        $res = $migrator->runOne($id, 'cli', null, true);
        exit($res['status'] === 'applied' ? 0 : 1);
    }

    if (isset($opts['all'])) {
        echo "Running all pending migrations..." . PHP_EOL;
        $count = 0;
        while (true) {
            $res = $migrator->runNext('cli', null, true);
            if ($res === null) {
                echo "No more pending migrations." . PHP_EOL;
                break;
            }
            $count++;
            if ($res['status'] === 'failed') {
                echo "Migration {$res['id']} failed. Aborting." . PHP_EOL;
                exit(1);
            }
        }
        echo "Completed $count migration execution(s)." . PHP_EOL;
        exit(0);
    }

    echo "Usage: php admin/db/migrate.php [--status | --run=<id> | --all] [--env=testing]" . PHP_EOL;
    exit(1);
}

// Web mode
chdir(dirname(__DIR__, 2));
require_once dirname(__DIR__, 2) . '/admin/include/generic_classes.php';
require_once dirname(__DIR__, 2) . '/admin/bootstrap_pgs.php';

if (!SessionData::superAdministrador() && !SessionData::hasPermission('sistema.migraciones.run')) {
    header('Location: ../../permiso_denegado.php');
    exit;
}

set_time_limit(120);

$migrator = new \Pgs\Migrations\Migrator();
$output = '';
$hasMore = false;
$activeId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        \Pgs\Core\Csrf::verify($_POST['csrf_token'] ?? null);
        $action = $_POST['action'] ?? '';
        $userId = (int) (SessionData::getUserId() ?? 0);

        if ($action === 'run_one') {
            $migId = (string) ($_POST['migration'] ?? '');
            $res = $migrator->runOne($migId, 'web', $userId);
            $output = $res['output'];
            $hasMore = $res['has_more'];
            $activeId = $res['id'];
        } elseif ($action === 'run_next') {
            $res = $migrator->runNext('web', $userId);
            if ($res !== null) {
                $output = $res['output'];
                $hasMore = $res['has_more'];
                $activeId = $res['id'];
            } else {
                $output = "No pending migrations found.";
            }
        }
    } catch (\Throwable $e) {
        $output = "ERROR: " . $e->getMessage();
    }
}

$statusList = $migrator->status();
$csrfToken = \Pgs\Core\Csrf::token();
$modulo = 'Database Migrations';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <base href="<?= htmlspecialchars(rtrim(str_replace('\\', '/', dirname((string) $_SERVER['SCRIPT_NAME'], 3)), '/') . '/', ENT_QUOTES) ?>">
  <?php include dirname(__DIR__, 2) . '/admin/include/generic_head.php'; ?>
  <title>Database Migrations</title>
  <style>
    .pgs-ui{ --brand:#E10600; --ink:#0B0F19; --muted:#64748b; --bg:#F6F7FB; --card:#fff; --border:#e7eaf1; }
    .pgs-ui .page-wrap{ padding: 6px 0 18px; }
    .pgs-ui .hero{
      border-radius: 24px; padding: 18px 20px; margin-bottom: 16px;
      background: linear-gradient(135deg, #0B0F19 0%, #1a1f2e 60%, #E10600 160%);
      color:#fff; box-shadow: 0 18px 60px rgba(2,6,23,.12);
    }
    .pgs-ui .hero h3{ margin:0; font-weight:1000; }
    .pgs-ui .hero p{ margin:6px 0 0; opacity:.85; }
    .pgs-ui .card{ border:1px solid var(--border); border-radius:18px; box-shadow:0 10px 26px rgba(2,6,23,.06); margin-bottom: 20px; }
    .pgs-migrate-out{ background:#0B0F19; color:#10b981; padding:16px; border-radius:12px; font-family:monospace; max-height:360px; overflow-y:auto; white-space:pre-wrap; }
    .badge-applied { background-color: #10b981; color: #fff; }
    .badge-pending { background-color: #f59e0b; color: #fff; }
    .badge-failed { background-color: #ef4444; color: #fff; }
    .badge-in_progress { background-color: #3b82f6; color: #fff; }
    .badge-checksum { background-color: #8b5cf6; color: #fff; }
  </style>
</head>
<body>
<div id="main-wrapper">
  <?php include dirname(__DIR__, 2) . '/admin/include/generic_header.php'; ?>

  <div class="deznav">
    <div class="deznav-scroll">
      <?php include dirname(__DIR__, 2) . '/admin/include/generic_navbar.php'; ?>
    </div>
  </div>

  <div class="content-body">
    <div class="container-fluid">
      <div class="pgs-ui">
        <div class="page-wrap">
          <div class="hero d-flex justify-content-between align-items-center flex-wrap">
            <div>
              <h3>Database Migrations</h3>
              <p>Execute and track system database migrations safely.</p>
            </div>
            <div>
              <form method="POST" style="display:inline-block;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="run_next">
                <button type="submit" class="btn btn-light" style="font-weight:900;">Run Next Pending</button>
              </form>
            </div>
          </div>

          <?php if ($output !== ''): ?>
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h5 class="mb-0">Execution Output</h5>
              <?php if ($hasMore && $activeId !== null): ?>
              <form method="POST" style="display:inline-block;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="run_one">
                <input type="hidden" name="migration" value="<?php echo htmlspecialchars($activeId); ?>">
                <button type="submit" class="btn btn-warning btn-sm font-weight-bold">Continue Next Batch</button>
              </form>
              <?php endif; ?>
            </div>
            <div class="card-body">
              <pre class="pgs-migrate-out"><?php echo htmlspecialchars($output); ?></pre>
            </div>
          </div>
          <?php endif; ?>

          <div class="card">
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-bordered table-striped verticle-middle table-responsive-sm">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Description</th>
                      <th>Status</th>
                      <th>Applied at</th>
                      <th>Duration</th>
                      <th>By</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($statusList as $m): ?>
                    <tr>
                      <td><strong><?php echo htmlspecialchars($m['id']); ?></strong></td>
                      <td><?php echo htmlspecialchars($m['description']); ?></td>
                      <td>
                        <?php
                        $badgeClass = match ($m['status']) {
                            'applied' => 'badge-applied',
                            'failed' => 'badge-failed',
                            'in_progress' => 'badge-in_progress',
                            'checksum changed' => 'badge-checksum',
                            default => 'badge-pending'
                        };
                        ?>
                        <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($m['status']); ?></span>
                      </td>
                      <td><?php echo htmlspecialchars((string) ($m['applied_at'] ?? '-')); ?></td>
                      <td><?php echo $m['duration_ms'] !== null ? htmlspecialchars((string) $m['duration_ms']) . ' ms' : '-'; ?></td>
                      <td><?php echo htmlspecialchars((string) ($m['applied_by_user_id'] ?? '-')); ?></td>
                      <td>
                        <?php if ($m['status'] !== 'applied' || $m['batched']): ?>
                        <form method="POST" style="display:inline-block;">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                          <input type="hidden" name="action" value="run_one">
                          <input type="hidden" name="migration" value="<?php echo htmlspecialchars($m['id']); ?>">
                          <button type="submit" class="btn btn-primary btn-xs">Run</button>
                        </form>
                        <?php else: ?>
                        <span class="text-muted"><i class="fa fa-check"></i></span>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<?php include dirname(__DIR__, 2) . '/admin/include/gerenic_script.php'; ?>
</body>
</html>
