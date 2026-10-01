<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/session.php';
start_app_session();
$isSignedIn = isset($_SESSION['user_id']);
?>
<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="theme-color" content="#f4f7f5">
		<title>Your wall | Neechan</title>
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Newsreader:opsz,wght@6..72,500;6..72,600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="data.css">
	</head>
	<body>
		<header class="topbar">
			<a class="brand" href="data.php" aria-label="Neechan home">
				<span class="brand-mark" aria-hidden="true">n</span>
				<span>neechan</span>
			</a>
			<div class="topbar-note"><span class="status-dot" aria-hidden="true"></span> A shared wall</div>
			<a class="account-link" href="<?= $isSignedIn ? 'account.php' : '../register_portal/login-page/account.html' ?>"><?= $isSignedIn ? 'Account' : 'Sign in' ?></a>
		</header>

		<main class="page-layout">
			<section class="main-column" aria-labelledby="page-title">
				<div class="page-heading">
					<div>
						<p class="eyebrow">A LITTLE SPACE WE SHARE</p>
						<h1 id="page-title">Your wall<span>.</span></h1>
					</div>
					<span class="heading-date" id="today-date"></span>
				</div>

				<section class="composer" aria-labelledby="composer-title">
					<div class="composer-heading">
						<div class="avatar" aria-hidden="true">Y</div>
						<div>
							<h2 id="composer-title">Make a post</h2>
							<p>Posts are visible to everyone.</p>
						</div>
					</div>
					<form id="post-form">
						<label class="visually-hidden" for="post-text">Write a post</label>
						<textarea id="post-text" name="text" maxlength="2000" placeholder="Write something worth remembering..." rows="4"></textarea>
						<div id="photo-preview" class="photo-preview" hidden>
							<img id="preview-image" alt="Selected photo preview">
							<button id="remove-photo" class="remove-photo" type="button" aria-label="Remove selected photo">Remove photo</button>
						</div>
						<div class="composer-footer">
							<div class="composer-tools">
								<label class="photo-button" for="photo-file"><span aria-hidden="true">＋</span> Add photo</label>
								<input id="photo-file" class="visually-hidden" type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp">
								<span class="char-count"><span id="char-count">0</span>/2000</span>
							</div>
							<button class="post-button" type="submit">Post <span aria-hidden="true">→</span></button>
						</div>
						<p id="composer-message" class="composer-message" role="status" aria-live="polite"></p>
					</form>
				</section>

				<section class="feed" aria-labelledby="feed-title">
					<div class="feed-heading">
						<h2 id="feed-title">Community posts</h2>
						<span id="post-count" class="post-count">0 posts</span>
					</div>
					<div id="post-list" class="post-list" aria-live="polite"></div>
					<div id="empty-state" class="empty-state">
						<span class="empty-mark" aria-hidden="true">✳</span>
						<h3>Your wall is ready</h3>
						<p>Be the first to share a thought or photo with everyone.</p>
					</div>
				</section>
			</section>

			<aside class="side-column" aria-label="Your archive">
				<section class="archive-panel">
					<p class="eyebrow">THE SHARED WALL</p>
					<p class="archive-count" id="archive-count">0</p>
					<p class="archive-label">shared posts</p>
					<div class="archive-divider"></div>
					<p class="storage-label"><span class="status-dot" aria-hidden="true"></span> Shared with everyone</p>
					<p class="storage-note">Posts and photos are visible to every visitor.</p>
				</section>
				<p class="side-caption">Small moments count, too.</p>
			</aside>
		</main>

		<template id="post-template">
			<article class="post-item">
				<div class="post-topline">
					<div class="post-author">
						<span class="avatar post-avatar" aria-hidden="true">Y</span>
						<div><p class="author-name">You</p><time class="post-time"></time></div>
					</div>
					<button class="delete-post" type="button" aria-label="Delete post" title="Delete post">Delete</button>
				</div>
				<p class="post-content"></p>
				<img class="post-image" alt="Photo attached to this post" hidden>
			</article>
		</template>

		<script>
			const postsUrl = 'posts.php';
			const maxPhotoBytes = 1.5 * 1024 * 1024;
			const form = document.querySelector('#post-form');
			const textInput = document.querySelector('#post-text');
			const photoInput = document.querySelector('#photo-file');
			const preview = document.querySelector('#photo-preview');
			const previewImage = document.querySelector('#preview-image');
			const message = document.querySelector('#composer-message');
			const postList = document.querySelector('#post-list');
			const emptyState = document.querySelector('#empty-state');
			const countLabel = document.querySelector('#post-count');
			const archiveCount = document.querySelector('#archive-count');
			const postTemplate = document.querySelector('#post-template');
			let posts = [];
			let csrfToken = null;

			document.querySelector('#today-date').textContent = new Intl.DateTimeFormat(undefined, {
				weekday: 'long', month: 'long', day: 'numeric'
			}).format(new Date());

			function showMessage(text, isError = false) {
				message.textContent = text;
				message.classList.toggle('is-error', isError);
			}

			function renderPosts() {
				postList.replaceChildren();

				for (const post of posts) {
					const fragment = postTemplate.content.cloneNode(true);
					const item = fragment.querySelector('.post-item');
					const content = fragment.querySelector('.post-content');
					const time = fragment.querySelector('.post-time');
					const image = fragment.querySelector('.post-image');
					const deleteButton = fragment.querySelector('.delete-post');

					content.textContent = post.body;
					content.hidden = !post.body;
					time.dateTime = new Date(post.created_at * 1000).toISOString();
					time.textContent = new Intl.DateTimeFormat(undefined, {
						dateStyle: 'medium', timeStyle: 'short'
					}).format(new Date(post.created_at * 1000));
					fragment.querySelector('.author-name').textContent = post.author;
					fragment.querySelector('.post-avatar').textContent = post.author.trim().charAt(0).toUpperCase() || '?';
					if (post.photo_url) {
						image.src = post.photo_url;
						image.hidden = false;
					}
					deleteButton.hidden = !post.can_delete;
					if (post.can_delete) deleteButton.addEventListener('click', () => deletePost(post.id));
					item.dataset.postId = post.id;
					postList.append(fragment);
				}

				const hasPosts = posts.length > 0;
				emptyState.hidden = hasPosts;
				countLabel.textContent = `${posts.length} ${posts.length === 1 ? 'post' : 'posts'}`;
				archiveCount.textContent = String(posts.length);
			}

			async function readResponse(response) {
				const result = await response.json();
				if (!response.ok) throw new Error(result.error || 'The request could not be completed.');
				return result;
			}

			async function loadPosts() {
				const result = await readResponse(await fetch(postsUrl));
				posts = result.posts;
				csrfToken = result.csrf_token;
				form.querySelectorAll('textarea, input, button[type="submit"]').forEach((control) => {
					control.disabled = !result.authenticated;
				});
				if (!result.authenticated) showMessage('Sign in from Account to add a post.');
				renderPosts();
			}

			async function deletePost(postId) {
				try {
					const formData = new FormData();
					formData.set('action', 'delete');
					formData.set('post_id', String(postId));
					formData.set('csrf_token', csrfToken);
					await readResponse(await fetch(postsUrl, { method: 'POST', body: formData }));
					await loadPosts();
					showMessage('Post deleted.');
				} catch (error) {
					showMessage(error.message, true);
				}
			}

			photoInput.addEventListener('change', () => {
				const [file] = photoInput.files;
				showMessage('');
				if (!file) {
					preview.hidden = true;
					return;
				}
				if (!file.type.startsWith('image/')) {
					photoInput.value = '';
					showMessage('Choose an image file to attach.', true);
					return;
				}
				if (file.size > maxPhotoBytes || !['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) {
					photoInput.value = '';
					showMessage('Choose a JPEG, PNG, GIF, or WebP photo smaller than 1.5 MB.', true);
					return;
				}
				previewImage.src = URL.createObjectURL(file);
				preview.hidden = false;
			});

			document.querySelector('#remove-photo').addEventListener('click', () => {
				if (previewImage.src.startsWith('blob:')) URL.revokeObjectURL(previewImage.src);
				photoInput.value = '';
				previewImage.removeAttribute('src');
				preview.hidden = true;
				showMessage('');
			});

			textInput.addEventListener('input', () => {
				document.querySelector('#char-count').textContent = String(textInput.value.length);
			});

			form.addEventListener('submit', async (event) => {
				event.preventDefault();
				const postButton = form.querySelector('.post-button');
				postButton.disabled = true;
				try {
					const formData = new FormData(form);
					formData.set('csrf_token', csrfToken);
					await readResponse(await fetch(postsUrl, { method: 'POST', body: formData }));
					await loadPosts();
					form.reset();
					preview.hidden = true;
					previewImage.removeAttribute('src');
					document.querySelector('#char-count').textContent = '0';
					showMessage('Post shared with everyone.');
				} catch (error) {
					showMessage(error.message, true);
				} finally {
					postButton.disabled = !csrfToken;
				}
			});

			loadPosts().catch((error) => showMessage(error.message, true));
		</script>
	</body>
</html>
