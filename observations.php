<?php
require_once 'includes/db_config.php';
// public feed; submission requires login
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? null;

// get like counts and user's likes
$like_counts = [];
$user_likes = [];
if ($user_id) {
    $stmt = $conn->query("SELECT observation_id, COUNT(*) as count FROM observation_likes GROUP BY observation_id");
    if ($stmt) {
        while ($row = $stmt->fetch_assoc()) {
            $like_counts[$row['observation_id']] = $row['count'];
        }
    }
    $stmt = $conn->prepare("SELECT observation_id FROM observation_likes WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $user_likes[] = $row['observation_id'];
        }
        $stmt->close();
    }
}

// get comment counts
$comment_counts = [];
$stmt = $conn->query("SELECT observation_id, COUNT(*) as count FROM observation_comments GROUP BY observation_id");
if ($stmt) {
    while ($row = $stmt->fetch_assoc()) {
        $comment_counts[$row['observation_id']] = $row['count'];
    }
}

// recent public observations with like/comment counts
$observations = [];
$stmt = $conn->prepare("SELECT o.*, s.name AS satellite_name, u.username FROM observations o JOIN satellites s ON o.satellite_id = s.id JOIN users u ON o.user_id = u.id WHERE o.is_public = 1 ORDER BY o.observed_at DESC LIMIT 50");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    $observations = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

// fetch satellites for form: if logged in use user's watchlist, otherwise show popular active satellites
$satellites_for_select = [];
if ($user_id) {
    $stmt = $conn->prepare("SELECT s.id, s.name FROM watchlist w JOIN satellites s ON w.satellite_id = s.id WHERE w.user_id = ? ORDER BY s.name");
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $satellites_for_select = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }
}
if (empty($satellites_for_select)) {
    $res = $conn->query("SELECT id, name FROM satellites WHERE is_active = 1 ORDER BY name LIMIT 100");
    if ($res) $satellites_for_select = $res->fetch_all(MYSQLI_ASSOC);
}

$csrf = isLoggedIn() ? generateCSRFToken() : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Observations - SatTrack</title>
    <link rel="stylesheet" href="assets/css/styles.css" />
    <style>
        .observations-container { display:flex; gap:2rem; padding:2rem; }
        .obs-form { width:360px; background: var(--bg-secondary); padding:1rem; border-radius:8px; border:1px solid var(--border); position: sticky; top: 80px; height: fit-content; }
        .obs-feed { flex:1; max-width: 800px; }
        .obs-card { background: var(--bg-card); border:1px solid var(--border); padding:1.5rem; border-radius:8px; margin-bottom:1rem; transition: all 0.2s; }
        .obs-card:hover { border-color: var(--accent); box-shadow: 0 4px 12px rgba(99, 96, 255, 0.1); }
        .obs-card .meta { font-size:0.9rem; color: var(--text-secondary); margin-bottom: 0.5rem; }
        .obs-note { white-space:pre-wrap; color: var(--text-primary); margin: 1rem 0; line-height: 1.6; }
        .obs-actions { display: flex; gap: 1.5rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); }
        .obs-action-btn { display: flex; align-items: center; gap: 0.5rem; background: transparent; border: none; color: var(--text-secondary); cursor: pointer; padding: 0.5rem; border-radius: 6px; transition: all 0.2s; font-size: 0.9rem; }
        .obs-action-btn:hover { background: rgba(255,255,255,0.05); color: var(--text-primary); }
        .obs-action-btn.liked { color: var(--accent); }
        .obs-action-btn.liked:hover { background: rgba(99, 96, 255, 0.1); }
        .obs-comments { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); display: none; }
        .obs-comments.show { display: block; }
        .comment-form { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
        .comment-form input { flex: 1; padding: 0.5rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-primary); color: white; }
        .comment-form button { padding: 0.5rem 1rem; border-radius: 6px; background: var(--accent); color: white; border: none; cursor: pointer; }
        .comment-list { display: flex; flex-direction: column; gap: 0.75rem; }
        .comment-item { background: rgba(255,255,255,0.03); padding: 0.75rem; border-radius: 6px; }
        .comment-item .comment-author { font-weight: 600; color: var(--text-heading); margin-bottom: 0.25rem; }
        .comment-item .comment-text { color: var(--text-body); line-height: 1.5; }
        .comment-item .comment-time { font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.25rem; }
        .btn { padding:0.6rem 1rem; border-radius:6px; cursor:pointer; border: 1px solid var(--border); background: var(--bg-primary); color: white; }
        .btn-primary { background: var(--accent); color:white; border:none; }
        .info-box { margin-bottom: 1rem; padding: 1rem; background: rgba(255,255,255,0.03); border-radius: 6px; }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="nav-brand">SatTrack</a>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="passes.php">My Passes</a>
            <a href="observations.php" class="active">Observations</a>
            <?php if (isLoggedIn()): ?>
                <a href="profile.php" class="nav-avatar-link" title="Profile"><span class="nav-avatar"><?= strtoupper(substr(($username ?? ''), 0, 1)) ?></span></a>
                <a href="logout.php">Logout (<?= escape($username) ?>)</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
        </div>
    </nav>

    <main style="padding:2rem;">
        <h1>Observations Feed</h1>
        <p class="login-description">Share your satellite sightings and connect with other observers.</p>

        <div class="observations-container">
            <div class="obs-form">
                <?php if (!isLoggedIn()): ?>
                    <div class="info-box">Please <a href="login.php">log in</a> to submit an observation.</div>
                <?php else: ?>
                    <form id="observationForm">
                        <input type="hidden" name="csrf" value="<?= $csrf ?>" />
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="satellite" style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary);">Satellite</label>
                            <select id="satellite" name="satellite_id" required style="width: 100%; padding: 0.5rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-primary); color: white;">
                                <?php foreach($satellites_for_select as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= escape($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="observed_at" style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary);">Observed at (local)</label>
                            <input id="observed_at" type="datetime-local" name="observed_at" required style="width: 100%; padding: 0.5rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-primary); color: white;" />
                        </div>
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="visibility" style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary);">Visibility (1-5)</label>
                            <select id="visibility" name="visibility" required style="width: 100%; padding: 0.5rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-primary); color: white;">
                                <?php for ($i=1;$i<=5;$i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="notes" style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary);">Notes (optional)</label>
                            <textarea id="notes" name="notes" rows="4" style="width:100%; padding: 0.5rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-primary); color: white; resize: vertical;"></textarea>
                        </div>
                        <div style="display:flex; gap:0.5rem; align-items:center; margin-bottom:0.75rem;">
                            <label style="margin:0; color: var(--text-secondary);">Public</label>
                            <input type="checkbox" id="is_public" name="is_public" checked />
                        </div>
                        <div style="display:flex; gap:0.5rem;">
                            <button id="submitObs" class="btn btn-primary" type="submit">Submit Observation</button>
                            <button id="clearObs" class="btn" type="button">Clear</button>
                        </div>
                        <div id="obsMessage" style="margin-top:0.75rem; color:var(--text-secondary);"></div>
                    </form>
                <?php endif; ?>
            </div>

            <div class="obs-feed">
                <?php if (empty($observations)): ?>
                    <div class="info-box">No observations yet. Be the first to add one!</div>
                <?php else: ?>
                    <div id="feedList">
                        <?php foreach($observations as $o): 
                            $obs_id = $o['id'];
                            $like_count = $like_counts[$obs_id] ?? 0;
                            $is_liked = in_array($obs_id, $user_likes);
                            $comment_count = $comment_counts[$obs_id] ?? 0;
                        ?>
                            <div class="obs-card" data-id="<?= $obs_id ?>">
                                <div class="meta">
                                    <strong><?= escape($o['satellite_name']) ?></strong> &middot; 
                                    observed by <em><?= escape($o['username']) ?></em> on 
                                    <?= escape(date('Y-m-d H:i', strtotime($o['observed_at']))) ?>
                                </div>
                                <?php if (!empty($o['notes'])): ?>
                                    <div class="obs-note"><?= nl2br(escape($o['notes'])) ?></div>
                                <?php endif; ?>
                                <div class="meta" style="margin-top:0.5rem;">visibility: <?= escape($o['visibility_rating']) ?>/5</div>
                                
                                <div class="obs-actions">
                                    <button class="obs-action-btn like-btn <?= $is_liked ? 'liked' : '' ?>" data-obs-id="<?= $obs_id ?>">
                                        <span>❤️</span>
                                        <span class="like-count"><?= $like_count ?></span>
                                    </button>
                                    <button class="obs-action-btn comment-btn" data-obs-id="<?= $obs_id ?>">
                                        <span>💬</span>
                                        <span class="comment-count"><?= $comment_count ?></span>
                                    </button>
                                </div>
                                
                                <div class="obs-comments" id="comments-<?= $obs_id ?>">
                                    <?php if (isLoggedIn()): ?>
                                        <div class="comment-form">
                                            <input type="text" class="comment-input" placeholder="Write a comment..." data-obs-id="<?= $obs_id ?>" />
                                            <button class="comment-submit-btn" data-obs-id="<?= $obs_id ?>">Post</button>
                                        </div>
                                    <?php else: ?>
                                        <div style="padding: 1rem; text-align: center; color: var(--text-secondary);">
                                            <a href="login.php" style="color: var(--accent);">Log in</a> to comment
                                        </div>
                                    <?php endif; ?>
                                    <div class="comment-list" id="comment-list-<?= $obs_id ?>">
                                        <!-- Comments will be loaded here -->
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        const userId = <?= $user_id ? json_encode($user_id) : 'null' ?>;
        let csrfToken = <?= $csrf ? json_encode($csrf) : 'null' ?>;
        
        <?php if (isLoggedIn()): ?>
        // Submit observation with CSRF refresh retry
        document.getElementById('observationForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('submitObs');
            btn.disabled = true;

            async function sendObservation(retry = false) {
                const data = {
                    csrf: csrfToken,
                    satellite_id: document.getElementById('satellite').value,
                    observed_at: document.getElementById('observed_at').value,
                    visibility: document.getElementById('visibility').value,
                    notes: document.getElementById('notes').value,
                    is_public: document.getElementById('is_public').checked ? 1 : 0
                };

                const res = await fetch('api/add_observation.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
                const j = await res.json();
                const msg = document.getElementById('obsMessage');

                if (j.success) {
                    msg.textContent = 'Observation saved!';
                    // update csrf if provided
                    if (j.csrf_new) {
                        csrfToken = j.csrf_new;
                        const el = document.querySelector('input[name="csrf"]');
                        if (el) el.value = csrfToken;
                    }
                    location.reload(); // reload to show new observation
                    return;
                }

                // On invalid CSRF, try to fetch a fresh token and retry once
                if (!retry && j.message && j.message.toLowerCase().includes('csrf')) {
                    msg.textContent = 'Session token expired; refreshing token and retrying...';
                    try {
                        const r = await fetch('api/get_csrf.php');
                        const dj = await r.json();
                        if (dj.success && dj.csrf) {
                            csrfToken = dj.csrf;
                            const el = document.querySelector('input[name="csrf"]');
                            if (el) el.value = csrfToken;
                            return await sendObservation(true);
                        } else {
                            msg.textContent = 'failed to refresh token; please reload the page';
                        }
                    } catch (err) {
                        msg.textContent = 'failed to refresh token; please reload the page';
                    }
                } else {
                    msg.textContent = j.message || 'failed to save';
                }
            }

            await sendObservation(false);
            btn.disabled = false;
        });

        document.getElementById('clearObs')?.addEventListener('click', () => {
            document.getElementById('notes').value = '';
        });
        
        // Like functionality
        document.querySelectorAll('.like-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!userId) {
                    alert('Please log in to like observations');
                    return;
                }
                const obsId = btn.dataset.obsId;
                const res = await fetch('api/toggle_like.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ observation_id: obsId, csrf: csrfToken })
                });
                const data = await res.json();
                if (data.success) {
                    const countEl = btn.querySelector('.like-count');
                    countEl.textContent = data.like_count;
                    if (data.liked) {
                        btn.classList.add('liked');
                    } else {
                        btn.classList.remove('liked');
                    }
                    if (data.csrf_new) {
                        csrfToken = data.csrf_new;
                        const el = document.querySelector('input[name="csrf"]');
                        if (el) el.value = csrfToken;
                    }
                }
            });
        });
        
        // Comment toggle
        document.querySelectorAll('.comment-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const obsId = btn.dataset.obsId;
                const commentsDiv = document.getElementById('comments-' + obsId);
                const isVisible = commentsDiv.classList.contains('show');
                
                if (!isVisible) {
                    // Load comments
                    const res = await fetch(`api/get_comments.php?observation_id=${obsId}`);
                    const data = await res.json();
                    if (data.success) {
                        const listEl = document.getElementById('comment-list-' + obsId);
                        listEl.innerHTML = '';

                        // recursive renderer
                        function renderCommentNode(comment, container) {
                            const commentEl = document.createElement('div');
                            commentEl.className = 'comment-item';
                            commentEl.dataset.commentId = comment.id;

                            // author, text, time
                            const author = document.createElement('div');
                            author.className = 'comment-author';
                            author.textContent = comment.username;

                            const text = document.createElement('div');
                            text.className = 'comment-text';
                            text.innerHTML = escapeHtml(comment.comment_text);

                            const time = document.createElement('div');
                            time.className = 'comment-time';
                            time.textContent = new Date(comment.created_at).toLocaleString();

                            commentEl.appendChild(author);
                            commentEl.appendChild(text);

                            // action row (reply)
                            if (userId) {
                                const actionRow = document.createElement('div');
                                actionRow.style.marginTop = '0.5rem';
                                const replyBtn = document.createElement('button');
                                replyBtn.className = 'btn';
                                replyBtn.textContent = 'Reply';
                                replyBtn.addEventListener('click', () => {
                                    // toggle reply input
                                    if (commentEl.querySelector('.reply-row')) {
                                        commentEl.querySelector('.reply-row').remove();
                                        return;
                                    }
                                    const replyRow = document.createElement('div');
                                    replyRow.className = 'reply-row';
                                    replyRow.style.display = 'flex';
                                    replyRow.style.gap = '0.5rem';
                                    replyRow.style.marginTop = '0.5rem';
                                    const input = document.createElement('input');
                                    input.style.flex = '1';
                                    input.className = 'reply-input';
                                    input.placeholder = 'Write a reply...';
                                    const submit = document.createElement('button');
                                    submit.className = 'btn btn-primary';
                                    submit.textContent = 'Post';
                                    submit.addEventListener('click', async function sendReply(retry = false) {
                                        const textVal = input.value.trim();
                                        if (!textVal) return;
                                        submit.disabled = true;
                                        
                                        const res = await fetch('api/add_comment.php', {
                                            method: 'POST',
                                            headers: { 'Content-Type': 'application/json' },
                                            body: JSON.stringify({ observation_id: obsId, comment_text: textVal, parent_comment_id: comment.id, csrf: csrfToken })
                                        });
                                        const resj = await res.json();
                                        
                                        if (resj.success) {
                                            // add as first child under this comment
                                            comment.replies = comment.replies || [];
                                            comment.replies.unshift(resj.comment);
                                            // render replies container
                                            const repliesContainer = commentEl.querySelector('.replies');
                                            if (repliesContainer) {
                                                const newNode = document.createElement('div');
                                                newNode.className = 'comment-item';
                                                newNode.innerHTML = `\n                                                    <div class="comment-author">${escapeHtml(resj.comment.username)}</div>\n                                                    <div class="comment-text">${escapeHtml(resj.comment.comment_text)}</div>\n                                                    <div class="comment-time">Just now</div>\n                                                `;
                                                repliesContainer.insertBefore(newNode, repliesContainer.firstChild);
                                            }
                                            input.value = '';
                                            replyRow.remove();
                                            // update CSRF token if returned
                                            if (resj.csrf_new) {
                                                csrfToken = resj.csrf_new;
                                                const el = document.querySelector('input[name="csrf"]');
                                                if (el) el.value = csrfToken;
                                            }
                                            submit.disabled = false;
                                        } else if (!retry && resj.message && resj.message.toLowerCase().includes('csrf')) {
                                            // CSRF token expired - refresh and retry
                                            try {
                                                const r = await fetch('api/get_csrf.php');
                                                const dj = await r.json();
                                                if (dj.success && dj.csrf) {
                                                    csrfToken = dj.csrf;
                                                    const el = document.querySelector('input[name="csrf"]');
                                                    if (el) el.value = csrfToken;
                                                    return sendReply(true);
                                                } else {
                                                    alert('Failed to refresh token. Please reload the page.');
                                                    submit.disabled = false;
                                                }
                                            } catch (err) {
                                                alert('Failed to refresh token. Please reload the page.');
                                                submit.disabled = false;
                                            }
                                        } else {
                                            alert(resj.message || 'Failed to post');
                                            submit.disabled = false;
                                        }
                                    });
                                    replyRow.appendChild(input);
                                    replyRow.appendChild(submit);
                                    commentEl.appendChild(replyRow);
                                });
                                actionRow.appendChild(replyBtn);
                                commentEl.appendChild(actionRow);
                            }

                            // replies container
                            const repliesContainer = document.createElement('div');
                            repliesContainer.className = 'replies';
                            repliesContainer.style.marginTop = '0.75rem';
                            repliesContainer.style.paddingLeft = '0.75rem';
                            repliesContainer.style.borderLeft = '1px solid rgba(255,255,255,0.03)';

                            // render children
                            if (comment.replies && comment.replies.length) {
                                comment.replies.forEach(child => renderCommentNode(child, repliesContainer));
                            }

                            commentEl.appendChild(repliesContainer);

                            container.appendChild(commentEl);
                        }

                        data.comments.forEach(comment => renderCommentNode(comment, listEl));
                    }
                    commentsDiv.classList.add('show');
                } else {
                    commentsDiv.classList.remove('show');
                }
            });
        });
        
// Submit comment (top-level)
        document.querySelectorAll('.comment-submit-btn').forEach(btn => {
            btn.addEventListener('click', async function sendComment(retry = false) {
                const obsId = btn.dataset.obsId;
                const input = document.querySelector(`.comment-input[data-obs-id="${obsId}"]`);
                const text = input.value.trim();
                if (!text) return;
                
                btn.disabled = true;

                const res = await fetch('api/add_comment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ observation_id: obsId, comment_text: text, csrf: csrfToken })
                });
                const data = await res.json();
                
                if (data.success) {
                    input.value = '';
                    // update CSRF token if returned
                    if (data.csrf_new) {
                        csrfToken = data.csrf_new;
                        const el = document.querySelector('input[name="csrf"]');
                        if (el) el.value = csrfToken;
                    }

                    // prepend new comment to the list
                    const listEl = document.getElementById('comment-list-' + obsId);

                    // if comments panel isn't open, open it and load
                    const commentsDiv = document.getElementById('comments-' + obsId);
                    if (!commentsDiv.classList.contains('show')) {
                        document.querySelector(`.comment-btn[data-obs-id="${obsId}"]`).click();
                    } else {
                        const dummy = { id: data.comment.id, username: data.comment.username, comment_text: data.comment.comment_text, created_at: data.comment.created_at, replies: [] };
                        // render on top
                        const firstChild = listEl.firstChild;
                        const temp = document.createElement('div');
                        temp.className = 'comment-item';
                        temp.innerHTML = `<div class="comment-author">${escapeHtml(dummy.username)}</div><div class="comment-text">${escapeHtml(dummy.comment_text)}</div><div class="comment-time">Just now</div>`;
                        if (firstChild) listEl.insertBefore(temp, firstChild); else listEl.appendChild(temp);
                    }

                    // Update comment count
                    const countEl = document.querySelector(`.comment-btn[data-obs-id="${obsId}"] .comment-count`);
                    countEl.textContent = parseInt(countEl.textContent) + 1;
                    btn.disabled = false;
                } else if (!retry && data.message && data.message.toLowerCase().includes('csrf')) {
                    // CSRF token expired - refresh and retry
                    try {
                        const r = await fetch('api/get_csrf.php');
                        const dj = await r.json();
                        if (dj.success && dj.csrf) {
                            csrfToken = dj.csrf;
                            const el = document.querySelector('input[name="csrf"]');
                            if (el) el.value = csrfToken;
                            return sendComment(true);
                        } else {
                            alert('Failed to refresh token. Please reload the page.');
                            btn.disabled = false;
                        }
                    } catch (err) {
                        alert('Failed to refresh token. Please reload the page.');
                        btn.disabled = false;
                    }
                } else {
                    alert(data.message || 'Failed to post comment');
                    btn.disabled = false;
                }
            });
        });
        
        // Allow Enter key to submit comment
        document.querySelectorAll('.comment-input').forEach(input => {
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    const btn = document.querySelector(`.comment-submit-btn[data-obs-id="${input.dataset.obsId}"]`);
                    btn.click();
                }
            });
        });
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        <?php endif; ?>
    </script>
</body>
</html>
