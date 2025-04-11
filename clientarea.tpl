{if isset($messages) && $messages|@count}
    {foreach from=$messages item=msg}
        {if $msg.success}
            <div class="alert alert-success">{$msg.success}</div>
        {/if}
        {if $msg.error}
            <div class="alert alert-danger">{$msg.error}</div>
        {/if}
    {/foreach}
{/if}

<h3>Server Details</h3>
<ul>
    {if $vmInfo.hostname}
        <li><strong>Hostname:</strong> {$vmInfo.hostname|escape}</li>
    {/if}
    {if $primaryIp}
        <li><strong>Main IP:</strong> {$primaryIp|escape}</li>
    {/if}
    {if $vmInfo.plan}
        <li><strong>Plan:</strong> {$vmInfo.plan|escape}</li>
    {/if}
    {if $vmInfo.state}
        <li><strong>Status:</strong> {$vmInfo.state|escape}</li>
    {/if}
</ul>

<h3>Rescue Mode</h3>
{if $rescueActive}
    <p>Your VPS is currently in <strong>Rescue Mode</strong>. Use the following root password to access the rescue environment:</p>
    <p><code>{$rescuePassword|escape}</code></p>
    <form method="post">
        <input type="hidden" name="modaction" value="disableRescue" />
        <button type="submit" class="btn btn-warning">Disable Rescue Mode</button>
    </form>
{else}
    <p>Your VPS is currently in normal mode. Enabling rescue mode will reboot the server into a recovery environment for troubleshooting.</p>
    <form method="post">
        <input type="hidden" name="modaction" value="enableRescue" />
        <button type="submit" class="btn btn-danger">Enable Rescue Mode</button>
    </form>
{/if}

<h3>Snapshots</h3>
{if !empty($snapshot.id)}
    <p>A snapshot was taken on <strong>{$snapshot.created_at}</strong>.
    {if $snapshot.expires_at} It will expire on <strong>{$snapshot.expires_at}</strong>.{/if}</p>
    <form method="post" style="margin-bottom:5px;">
        <input type="hidden" name="modaction" value="createSnapshot" />
        <button type="submit" class="btn btn-secondary">Create New Snapshot (Overwrite)</button>
    </form>
    <form method="post" style="display:inline-block; margin-right:5px;">
        <input type="hidden" name="modaction" value="restoreSnapshot" />
        <button type="submit" class="btn btn-primary">Restore Snapshot</button>
    </form>
    <form method="post" style="display:inline-block;">
        <input type="hidden" name="modaction" value="deleteSnapshot" />
        <button type="submit" class="btn btn-danger">Delete Snapshot</button>
    </form>
{else}
    <p>No snapshot is currently stored for this VPS.</p>
    <form method="post">
        <input type="hidden" name="modaction" value="createSnapshot" />
        <button type="submit" class="btn btn-secondary">Create Snapshot</button>
    </form>
{/if}

<h3>Backups</h3>
{if $backups|@count}
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Backup Date</th>
                <th>Location</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            {foreach from=$backups item=backup}
            <tr>
                <td>{$backup.created_at}</td>
                <td>{$backup.location}</td>
                <td>
                    <form method="post" style="display:inline-block; margin-right:5px;">
                        <input type="hidden" name="modaction" value="restoreBackup" />
                        <input type="hidden" name="backup_id" value="{$backup.id}" />
                        <button type="submit" class="btn btn-primary btn-sm">Restore</button>
                    </form>
                    <form method="post" style="display:inline-block;">
                        <input type="hidden" name="modaction" value="deleteBackup" />
                        <input type="hidden" name="backup_id" value="{$backup.id}" />
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </td>
            </tr>
            {/foreach}
        </tbody>
    </table>
{else}
    <p>No backups available.</p>
{/if}
