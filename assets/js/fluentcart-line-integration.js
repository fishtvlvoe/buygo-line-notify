/**
 * FluentCart LINE Integration
 *
 * Vue 元件整合範例
 * 在 FluentCart 客戶檔案頁面中顯示 LINE 綁定狀態
 */

// API Base URL
const API_BASE = window.location.origin + '/wp-json/buygo-line-notify/v1/fluentcart';

/**
 * LINE 綁定狀態 Vue 元件（範例）
 *
 * 在 FluentCart Vue 應用中使用：
 *
 * import LineBindingStatus from './fluentcart-line-integration.js';
 *
 * export default {
 *   components: {
 *     LineBindingStatus
 *   }
 * }
 */
export const LineBindingStatus = {
	name: 'LineBindingStatus',

	data() {
		return {
			loading: true,
			isLinked: false,
			lineData: null,
			error: null,
		};
	},

	mounted() {
		this.fetchBindingStatus();
	},

	methods: {
		/**
		 * 取得 LINE 綁定狀態
		 */
		async fetchBindingStatus() {
			this.loading = true;
			this.error = null;

			try {
				const response = await fetch(`${API_BASE}/binding-status`, {
					method: 'GET',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
					},
				});

				const data = await response.json();

				if (data.success) {
					this.isLinked = data.is_linked;
					if (data.is_linked) {
						this.lineData = {
							lineUid: data.line_uid,
							displayName: data.display_name,
							avatarUrl: data.avatar_url,
							linkedAt: data.linked_at,
						};
					}
				} else {
					this.error = data.message || '取得綁定狀態失敗';
				}
			} catch (err) {
				console.error('Error fetching LINE binding status:', err);
				this.error = '發生錯誤，請稍後再試';
			} finally {
				this.loading = false;
			}
		},

		/**
		 * 綁定 LINE 帳號
		 */
		async bindLine() {
			this.loading = true;

			try {
				const redirectUrl = window.location.href; // 綁定後回到當前頁面

				const response = await fetch(
					`${API_BASE}/bind-url?redirect_url=${encodeURIComponent(redirectUrl)}`,
					{
						method: 'GET',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
						},
					}
				);

				const data = await response.json();

				if (data.success && data.authorize_url) {
					// 跳轉到 LINE 授權頁面
					window.location.href = data.authorize_url;
				} else {
					this.error = data.message || '取得授權 URL 失敗';
					this.loading = false;
				}
			} catch (err) {
				console.error('Error getting bind URL:', err);
				this.error = '發生錯誤，請稍後再試';
				this.loading = false;
			}
		},

		/**
		 * 解除 LINE 綁定
		 */
		async unbindLine() {
			if (!confirm('確定要解除 LINE 綁定嗎？\n\n解除後將無法使用 LINE 快速登入，且會刪除您的 LINE 綁定記錄。')) {
				return;
			}

			this.loading = true;

			try {
				const response = await fetch(`${API_BASE}/unbind`, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
					},
				});

				const data = await response.json();

				if (data.success) {
					// 成功解除綁定，重新載入狀態
					this.isLinked = false;
					this.lineData = null;
					alert('已成功解除 LINE 綁定');
				} else {
					this.error = data.message || '解除綁定失敗';
				}
			} catch (err) {
				console.error('Error unbinding LINE:', err);
				this.error = '發生錯誤，請稍後再試';
			} finally {
				this.loading = false;
			}
		},

		/**
		 * 格式化日期
		 */
		formatDate(dateString) {
			if (!dateString) return '';
			const date = new Date(dateString);
			return date.toLocaleDateString('zh-TW', {
				year: 'numeric',
				month: 'long',
				day: 'numeric',
			});
		},
	},

	template: `
		<div class="line-binding-status">
			<!-- 載入中 -->
			<div v-if="loading" class="loading">
				<span>載入中...</span>
			</div>

			<!-- 錯誤訊息 -->
			<div v-else-if="error" class="error-message">
				{{ error }}
			</div>

			<!-- 未綁定狀態 -->
			<div v-else-if="!isLinked" class="not-linked">
				<h3>LINE 帳號綁定</h3>
				<p>綁定 LINE 帳號後，您可以使用 LINE 快速登入。</p>
				<button @click="bindLine" class="btn btn-primary">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 8px;">
						<path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/>
					</svg>
					綁定 LINE 帳號
				</button>
			</div>

			<!-- 已綁定狀態 -->
			<div v-else class="linked">
				<h3>LINE 帳號綁定</h3>
				<div class="line-profile">
					<img v-if="lineData.avatarUrl" :src="lineData.avatarUrl" alt="LINE Avatar" class="line-avatar" />
					<div class="line-avatar-placeholder" v-else>
						<svg width="40" height="40" viewBox="0 0 24 24" fill="#999">
							<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
						</svg>
					</div>
					<div class="line-info">
						<p class="line-name"><strong>{{ lineData.displayName }}</strong></p>
						<p class="line-uid">LINE UID: {{ lineData.lineUid }}</p>
						<p class="line-date">綁定於：{{ formatDate(lineData.linkedAt) }}</p>
					</div>
				</div>
				<button @click="unbindLine" class="btn btn-danger" :disabled="loading">
					解除綁定
				</button>
			</div>
		</div>
	`,
};

/**
 * 純 JavaScript 版本（不依賴 Vue）
 *
 * 使用方式：
 * import { renderLineBindingWidget } from './fluentcart-line-integration.js';
 * renderLineBindingWidget('#line-binding-container');
 */
export function renderLineBindingWidget(containerSelector) {
	const container = document.querySelector(containerSelector);
	if (!container) {
		console.error(`Container ${containerSelector} not found`);
		return;
	}

	let state = {
		loading: true,
		isLinked: false,
		lineData: null,
		error: null,
	};

	// 渲染 UI
	function render() {
		if (state.loading) {
			container.innerHTML = '<div class="loading">載入中...</div>';
			return;
		}

		if (state.error) {
			container.innerHTML = `<div class="error-message">${state.error}</div>`;
			return;
		}

		if (!state.isLinked) {
			container.innerHTML = `
				<div class="line-binding-status not-linked">
					<h3>LINE 帳號綁定</h3>
					<p>綁定 LINE 帳號後，您可以使用 LINE 快速登入。</p>
					<button id="btn-bind-line" class="btn btn-primary">綁定 LINE 帳號</button>
				</div>
			`;
			document.getElementById('btn-bind-line').addEventListener('click', bindLine);
		} else {
			const { displayName, lineUid, avatarUrl, linkedAt } = state.lineData;
			const avatarHtml = avatarUrl
				? `<img src="${avatarUrl}" alt="LINE Avatar" class="line-avatar" />`
				: '<div class="line-avatar-placeholder">?</div>';

			container.innerHTML = `
				<div class="line-binding-status linked">
					<h3>LINE 帳號綁定</h3>
					<div class="line-profile">
						${avatarHtml}
						<div class="line-info">
							<p class="line-name"><strong>${displayName}</strong></p>
							<p class="line-uid">LINE UID: ${lineUid}</p>
							<p class="line-date">綁定於：${new Date(linkedAt).toLocaleDateString('zh-TW')}</p>
						</div>
					</div>
					<button id="btn-unbind-line" class="btn btn-danger">解除綁定</button>
				</div>
			`;
			document.getElementById('btn-unbind-line').addEventListener('click', unbindLine);
		}
	}

	// 取得綁定狀態
	async function fetchBindingStatus() {
		state.loading = true;
		render();

		try {
			const response = await fetch(`${API_BASE}/binding-status`, {
				method: 'GET',
				credentials: 'same-origin',
			});
			const data = await response.json();

			if (data.success) {
				state.isLinked = data.is_linked;
				if (data.is_linked) {
					state.lineData = {
						lineUid: data.line_uid,
						displayName: data.display_name,
						avatarUrl: data.avatar_url,
						linkedAt: data.linked_at,
					};
				}
			} else {
				state.error = data.message || '取得綁定狀態失敗';
			}
		} catch (err) {
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
			const redirectUrl = window.location.href;
			const response = await fetch(
				`${API_BASE}/bind-url?redirect_url=${encodeURIComponent(redirectUrl)}`,
				{ method: 'GET', credentials: 'same-origin' }
			);
			const data = await response.json();

			if (data.success && data.authorize_url) {
				window.location.href = data.authorize_url;
			} else {
				state.error = data.message || '取得授權 URL 失敗';
				state.loading = false;
				render();
			}
		} catch (err) {
			state.error = '發生錯誤，請稍後再試';
			state.loading = false;
			render();
		}
	}

	// 解除綁定
	async function unbindLine() {
		if (!confirm('確定要解除 LINE 綁定嗎？')) return;

		state.loading = true;
		render();

		try {
			const response = await fetch(`${API_BASE}/unbind`, {
				method: 'POST',
				credentials: 'same-origin',
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
			state.error = '發生錯誤，請稍後再試';
		} finally {
			state.loading = false;
			render();
		}
	}

	// 初始化
	fetchBindingStatus();
}
