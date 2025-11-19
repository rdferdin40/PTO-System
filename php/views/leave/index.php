<div class="requests-page">
    <h1>Leave Requests</h1>

    <div class="actions">
        <a href="/requests/new" class="btn btn-primary">Request New Leave</a>
    </div>

    <?php if (!empty($pending_approvals)): ?>
        <div class="section">
            <h2>Pending Approvals</h2>

            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Type</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_approvals as $leave): ?>
                        <tr>
                            <td><?= View::e($leave['user_name'] . ' ' . $leave['user_lastname']) ?></td>
                            <td><?= View::e($leave['leave_type_name']) ?></td>
                            <td><?= View::humanDate($leave['date_start']) ?></td>
                            <td><?= View::humanDate($leave['date_end']) ?></td>
                            <td><span class="status status-<?= $leave['status'] ?>"><?= Leave::getStatusName($leave['status']) ?></span></td>
                            <td>
                                <a href="/requests/<?= $leave['id'] ?>" class="btn-small">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="section">
        <h2>My Requests</h2>

        <?php if (empty($leaves)): ?>
            <p>No leave requests yet. <a href="/requests/new">Request your first leave</a>.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leaves as $leave): ?>
                        <tr>
                            <td><?= View::e($leave['leave_type_name'] ?? 'N/A') ?></td>
                            <td><?= View::humanDate($leave['date_start']) ?></td>
                            <td><?= View::humanDate($leave['date_end']) ?></td>
                            <td>
                                <span class="status status-<?= $leave['status'] ?>">
                                    <?= Leave::getStatusName($leave['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="/requests/<?= $leave['id'] ?>" class="btn-small">View</a>
                                <?php if (in_array($leave['status'], [Leave::STATUS_NEW, Leave::STATUS_APPROVED])): ?>
                                    <form method="POST" action="/requests/<?= $leave['id'] ?>/cancel" style="display: inline;">
                                        <?= View::csrfField() ?>
                                        <button type="submit" class="btn-small btn-danger" onclick="return confirm('Cancel this request?')">Cancel</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<style>
.requests-page {
    max-width: 1200px;
    margin: 0 auto;
}

.actions {
    margin: 1.5rem 0;
}

.section {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.section h2 {
    margin-bottom: 1rem;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th,
.table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}

.table th {
    background: #f5f5f5;
    font-weight: 600;
}

.table tr:hover {
    background: #f9f9f9;
}

.status {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.status-1 {
    background: #fff3cd;
    color: #856404;
}

.status-2 {
    background: #d4edda;
    color: #155724;
}

.status-3 {
    background: #f8d7da;
    color: #721c24;
}

.status-4 {
    background: #d1ecf1;
    color: #0c5460;
}

.status-5 {
    background: #e2e3e5;
    color: #383d41;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: #333;
    background: white;
    cursor: pointer;
    display: inline-block;
}

.btn-primary {
    background: #0066cc;
    color: white;
    border-color: #0066cc;
}

.btn-small {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: #333;
    background: white;
    cursor: pointer;
}

.btn-danger {
    color: #721c24;
    border-color: #f5c6cb;
}

.btn-danger:hover {
    background: #f8d7da;
}
</style>
