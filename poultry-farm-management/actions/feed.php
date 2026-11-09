<?php
require __DIR__ . '/../includes/bootstrap.php';
require_post_csrf(); $u = require_admin();
try {
    $type = post('type'); $d = post('txn_date'); $bags = str_replace(',', '.', post('bags'));
    if (!in_array($type, ['opening', 'purchase', 'adjustment'], true)) throw new UserError('Choose a record type.');
    if (!valid_date($d) || $d > today()) throw new UserError('Choose a valid date that is not in the future.');
    if (!is_numeric($bags) || abs((float)$bags) > 1000000) throw new UserError('Enter the number of bags.');
    $b = (float)$bags; if ($type !== 'adjustment' && $b <= 0) throw new UserError('Bags must be more than 0 for this type.'); if ($b == 0) throw new UserError('An adjustment of 0 does nothing.');
    q('INSERT INTO feed_inventory_transactions (txn_date, type, bags, reference, notes, created_by) VALUES (?,?,?,?,?,?)', [$d, $type, $b, post('reference') ?: null, post('notes') ?: null, $u['id']]);
    audit_log('feed.stock_' . $type, 'feed', (int)db()->lastInsertId(), null, ['date' => $d, 'bags' => $b]);
    flash('success', 'Stock record saved.');
} catch (UserError $e) { flash('error', $e->getMessage()); }
redirect('feed/index.php');
