/**
 * FluentCart LINE Integration (Standalone)
 *
 * 純 JavaScript 版本，不依賴 Vue
 * 自動在 #buygo-line-binding-widget 中渲染 LINE 綁定狀態
 */

(function() {
	'use strict';

	// 等待 DOM 載入完成
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	function init() {
		// 支援多個容器（使用 class 或 ID）
		const containers = document.querySelectorAll('.buygo-line-binding-widget, #buygo-line-binding-widget');
		if (containers.length === 0) {
			console.log('LINE binding widget container not found');
			return;
		}

		// 初始化所有容器
		containers.forEach(initContainer);
	}

	function initContainer(container) {
		if (!container) {
			return;
		}

		// 從 WordPress localize script 取得 API base URL
		const apiBase = window.buygoLineFluentCart?.apiBase ||
		                window.location.origin + '/wp-json/buygo-line-notify/v1/fluentcart';

		// Widget 狀態
		let state = {
			loading: true,
			isLinked: false,
			lineData: null,
			error: null
		};

		// 渲染 UI
		function render() {
			if (state.loading) {
				container.innerHTML = '<div class="loading">載入中...</div>';
				return;
			}

			if (state.error) {
				container.innerHTML = `
					<div class="line-binding-status">
						<div class="error-message">${escapeHtml(state.error)}</div>
					</div>
				`;
				return;
			}

			if (!state.isLinked) {
				container.innerHTML = `
					<div class="line-binding-status not-linked">
						<h3>LINE 帳號綁定</h3>
						<p style="margin-bottom: 16px; color: #666;">綁定 LINE 帳號後，您可以使用 LINE 快速登入。</p>
						<button id="btn-bind-line" class="btn btn-primary">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
								<path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/>
							</svg>
							綁定 LINE 帳號
						</button>
					</div>
				`;

				const bindBtn = document.getElementById('btn-bind-line');
				if (bindBtn) {
					bindBtn.addEventListener('click', bindLine);
				}
			} else {
				const { displayName, lineUid, avatarUrl, linkedAt } = state.lineData;
				const avatarHtml = avatarUrl
					? `<img src="${escapeHtml(avatarUrl)}" alt="LINE Avatar" class="line-avatar" />`
					: `<div class="line-avatar-placeholder">?</div>`;

				const formattedDate = linkedAt ? formatDate(linkedAt) : '未知';

				container.innerHTML = `
					<div class="line-binding-status linked">
						<h3>LINE 帳號綁定</h3>
						<div class="line-profile">
							${avatarHtml}
							<div class="line-info">
								<p class="line-name">${escapeHtml(displayName)}</p>
								<p class="line-uid">LINE UID: ${escapeHtml(lineUid)}</p>
								<p class="line-date">綁定於：${escapeHtml(formattedDate)}</p>
							</div>
						</div>
						<button id="btn-unbind-line" class="btn btn-danger">解除綁定</button>
					</div>
				`;

				const unbindBtn = document.getElementById('btn-unbind-line');
				if (unbindBtn) {
					unbindBtn.addEventListener('click', unbindLine);
				}
			}
		}

		// 取得綁定狀態
		async function fetchBindingStatus() {
			state.loading = true;
			render();

			try {
				const response = await fetch(`${apiBase}/binding-status`, {
					method: 'GET',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': window.buygoLineFluentCart?.nonce || ''
					}
				});

				const data = await response.json();

				if (data.success) {
					state.isLinked = data.is_linked;
					if (data.is_linked) {
						state.lineData = {
							lineUid: data.line_uid,
							displayName: data.display_name,
							avatarUrl: data.avatar_url,
							linkedAt: data.linked_at
						};
					}
				} else {
					state.error = data.message || '取得綁定狀態失敗';
				}
			} catch (err) {
				console.error('Error fetching LINE binding status:', err);
				state.error = '發生錯誤，請稍後再試';
			} finally {
				state.loading = false;
				render();
			}
		}

		// 綁定 LINE
		async function bindLine() {
			state.loading = true;
			render();

			try {
				// 使用 data attribute 或當前頁面 URL
				const redirectUrl = container.dataset.redirectUrl || window.location.href;
				const response = await fetch(
					`${apiBase}/bind-url?redirect_url=${encodeURIComponent(redirectUrl)}`,
					{
						method: 'GET',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoLineFluentCart?.nonce || ''
						}
					}
				);

				const data = await response.json();

				if (data.success && data.authorize_url) {
					// 跳轉到 LINE 授權頁面
					window.location.href = data.authorize_url;
				} else {
					state.error = data.message || '取得授權 URL 失敗';
					state.loading = false;
					render();
				}
			} catch (err) {
				console.error('Error getting bind URL:', err);
				state.error = '發生錯誤，請稍後再試';
				state.loading = false;
				render();
			}
		}

		// 解除綁定
		async function unbindLine() {
			if (!confirm('確定要解除 LINE 綁定嗎？\n\n解除後將無法使用 LINE 快速登入。')) {
				return;
			}

			state.loading = true;
			render();

			try {
				const response = await fetch(`${apiBase}/unbind`, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': window.buygoLineFluentCart?.nonce || ''
					}
				});

				const data = await response.json();

				if (data.success) {
					state.isLinked = false;
					state.lineData = null;
					alert('已成功解除 LINE 綁定');
				} else {
					state.error = data.message || '解除綁定失敗';
				}
			} catch (err) {
				console.error('Error unbinding LINE:', err);
				state.error = '發生錯誤，請稍後再試';
			} finally {
				state.loading = false;
				render();
			}
		}

		// 工具函數：HTML 跳脫
		function escapeHtml(text) {
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		}

		// 工具函數：格式化日期
		function formatDate(dateString) {
			if (!dateString) return '';

			try {
				const date = new Date(dateString);
				return date.toLocaleDateString('zh-TW', {
					year: 'numeric',
					month: 'long',
					day: 'numeric'
				});
			} catch (e) {
				return dateString;
			}
		}

		// 初始化：取得綁定狀態
		fetchBindingStatus();
	}
})();
