(function () {
  "use strict";

  const AUTH_STORAGE_KEY = window.GM_STORAGE_KEYS.user;
  const SLOT_ID = "navAuthSlot";
  const API_BASE = "/api/auth";
  const TIMEOUT_MS = 15000;

  let slotEl = null;
  let loginTemplateHTML = null;
  let csrfPromise = null;

  function getXsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : null;
  }

  function ensureCsrfCookie(force) {
    if (!force && getXsrfToken()) return Promise.resolve();
    if (!csrfPromise) {
      csrfPromise = fetch(API_BASE + "/me", {
        method: "GET",
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        credentials: "same-origin",
        cache: "no-store",
      })
        .then(() => {}, () => {})
        .then(() => { csrfPromise = null; });
    }
    return csrfPromise;
  }

  function send(method, path, body) {
    const controller = typeof AbortController === "function" ? new AbortController() : null;
    const timer = controller ? window.setTimeout(() => controller.abort(), TIMEOUT_MS) : null;

    const prepare = method === "GET" ? Promise.resolve() : ensureCsrfCookie(false);

    return prepare
      .then(() => {
        const headers = { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" };
        const init = {
          method: method,
          headers: headers,
          credentials: "same-origin",
          cache: "no-store",
          signal: controller ? controller.signal : undefined,
        };
        if (method !== "GET") {
          headers["Content-Type"] = "application/json";
          const xsrf = getXsrfToken();
          if (xsrf) headers["X-XSRF-TOKEN"] = xsrf;
          init.body = JSON.stringify(body || {});
        }
        return fetch(API_BASE + path, init);
      })
      .then(
        (response) =>
          response.json().then(
            (payload) => ({ status: response.status, payload: payload }),
            () => ({ status: response.status, payload: {} })
          ),
        () => ({ status: 0, payload: {} })
      )
      .then((result) => {
        window.clearTimeout(timer);
        return result;
      });
  }

  function request(method, path, body) {
    return send(method, path, body).then((res) => {
      if (res.status === 419 && method !== "GET") {
        return ensureCsrfCookie(true).then(() => send(method, path, body));
      }
      return res;
    });
  }

  function toResult(res, mapData) {
    const body = res.payload || {};

    if (res.status === 0) return { ok: false, code: "NETWORK", status: 0, data: {} };

    if (res.status >= 200 && res.status < 300) {
      return { ok: true, status: res.status, data: mapData ? mapData(body.data || {}, res.status) : {} };
    }

    let code = body.code;
    if (!code) {
      if (res.status === 401) code = "UNAUTHENTICATED";
      else if (res.status === 429) code = "RATE_LIMITED";
      else if (res.status === 419) code = "CSRF";
      else code = "SERVER";
    }

    return {
      ok: false,
      code: code,
      status: res.status,
      data: body,
      retryAfter: Number(body.retry_after) || 0,
      errors: body.errors || null,
    };
  }

  function toUser(d) {
    if (!d || !d.phone) return null;
    return {
      id: d.id,
      name: d.full_name || "",
      phone: d.phone,
      avatar: d.avatar_url || "",
    };
  }

  const session = {
    requestOtp: function (params) {
      const body = { phone: params.phone, purpose: params.purpose };
      if (params.purpose === "register") body.full_name = params.fullName || "";
      return request("POST", "/otp/request", body).then((res) =>
        toResult(res, (d) => ({
          expiresIn: Number(d.expires_in) || 300,
          resendAfter: Number(d.resend_after) || 60,
        }))
      );
    },

    verifyOtp: function (params) {
      return request("POST", "/otp/verify", {
        phone: params.phone,
        purpose: params.purpose,
        code: params.code,
      }).then((res) =>
        toResult(res, (d, status) => ({ user: toUser(d), created: status === 201 }))
      );
    },

    me: function () {
      return request("GET", "/me").then((res) => toResult(res, (d) => ({ user: toUser(d) })));
    },

    logout: function () {
      return request("POST", "/logout", {}).then((res) => {
        if (res.status === 401 || res.status === 419) return { ok: true, status: res.status, data: {} };
        return toResult(res);
      });
    },
  };

  window.GMSession = Object.freeze(session);

  function getUser() {
    try {
      const raw = localStorage.getItem(AUTH_STORAGE_KEY);
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      if (!parsed || !parsed.name) return null;
      return parsed;
    } catch (e) {
      console.warn("[auth.js] Failed to parse user from localStorage:", e);
      return null;
    }
  }

  function clearUser() {
    localStorage.removeItem(AUTH_STORAGE_KEY);
  }

  function saveServerUser(user) {
    try {
      const existing = JSON.parse(localStorage.getItem(AUTH_STORAGE_KEY) || "null");
      const same = existing && existing.phone === user.phone ? existing : null;
      const rawRegion = typeof window.gmGetSelectedArea === "function" ? window.gmGetSelectedArea() : "";
      const region = typeof window.gmNormalizeRegionKey === "function" ? window.gmNormalizeRegionKey(rawRegion) : rawRegion;

      localStorage.setItem(
        AUTH_STORAGE_KEY,
        JSON.stringify({
          ...same,
          id: user.id,
          name: user.name || user.phone,
          phone: user.phone,
          avatar: user.avatar || "",
          region: same?.region || region || "",
          createdAt: same?.createdAt || new Date().toISOString(),
        })
      );
    } catch (e) {
      console.warn("[auth.js] Failed to save user:", e);
    }
  }

  function syncWithServer() {
    session.me().then((res) => {
      if (res.ok && res.data.user) {
        saveServerUser(res.data.user);
        render();
        return;
      }
      if (res.code === "UNAUTHENTICATED" && getUser()) {
        clearUser();
        render();
        document.dispatchEvent(new CustomEvent("gmUserLoggedOut"));
      }
    });
  }

  function renderLoggedOut() {
    if (!slotEl || loginTemplateHTML === null) return;
    slotEl.innerHTML = loginTemplateHTML;
  }

  function renderLoggedIn(user) {
    if (!slotEl) return;

    const tpl = document.getElementById("authxLoggedInTemplate");
    if (!tpl) {
      console.warn("[auth.js] #authxLoggedInTemplate not found in the page.");
      return;
    }

    const initial = user.name.trim().charAt(0).toUpperCase();
    const fragment = tpl.content.cloneNode(true);

    const avatarBtn = fragment.querySelector("#authxAvatarBtn");
    const avatarLetter = fragment.querySelector('[data-role="avatar-letter"]');
    const nameEl = fragment.querySelector('[data-role="user-name"]');
    const phoneEl = fragment.querySelector('[data-role="user-phone"]');

    if (avatarBtn) avatarBtn.title = user.name;

    if (user.avatar && avatarLetter) {
      const avatarImg = document.createElement("img");
      avatarImg.className = "authx-avatar-img";
      avatarImg.src = user.avatar;
      avatarImg.alt = user.name;
      avatarLetter.replaceWith(avatarImg);
    } else if (avatarLetter) {
      avatarLetter.textContent = initial;
    }

    if (nameEl) nameEl.textContent = user.name;

    if (phoneEl) {
      if (user.phone) {
        phoneEl.textContent = user.phone;
      } else {
        phoneEl.remove();
      }
    }

    slotEl.innerHTML = "";
    slotEl.appendChild(fragment);

    bindDropdownEvents();
  }

  function bindDropdownEvents() {
    const avatarBtn = document.getElementById("authxAvatarBtn");
    const dropdown = document.getElementById("authxDropdown");
    const logoutBtn = document.getElementById("authxLogoutBtn");
    const userMenu = slotEl.querySelector(".authx-user-menu");

    if (!avatarBtn || !dropdown || !userMenu) return;

    function openMenu() {
      userMenu.classList.add("authx-open");
      avatarBtn.setAttribute("aria-expanded", "true");
      document.addEventListener("click", handleOutsideClick);
      document.addEventListener("keydown", handleEscape);
    }

    function closeMenu() {
      userMenu.classList.remove("authx-open");
      avatarBtn.setAttribute("aria-expanded", "false");
      document.removeEventListener("click", handleOutsideClick);
      document.removeEventListener("keydown", handleEscape);
    }

    function handleOutsideClick(e) {
      if (!userMenu.contains(e.target)) closeMenu();
    }

    function handleEscape(e) {
      if (e.key === "Escape") closeMenu();
    }

    avatarBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      const isOpen = userMenu.classList.contains("authx-open");
      isOpen ? closeMenu() : openMenu();
    });

    if (logoutBtn) {
      logoutBtn.addEventListener("click", function (e) {
        e.preventDefault();
        performLogout();
      });
    }
  }

  function performLogout() {
    clearUser();
    renderLoggedOut();

    document.dispatchEvent(new CustomEvent("gmUserLoggedOut"));

    session.logout();
  }

  function render() {
    const user = getUser();
    if (user) {
      renderLoggedIn(user);
    } else {
      renderLoggedOut();
    }
  }

  function init() {
    slotEl = document.getElementById(SLOT_ID);
    if (!slotEl) {
      console.warn(`[auth.js] #${SLOT_ID} not found. Auth UI not initialized.`);
      return;
    }

    if (loginTemplateHTML === null) {
      loginTemplateHTML = slotEl.innerHTML;
    }

    render();
    syncWithServer();
  }

  function refresh() {
    if (!slotEl) {
      init();
      return;
    }
    render();
  }

  document.addEventListener("DOMContentLoaded", init);

  window.AuthUI = {
    refresh: refresh,
    logout: performLogout,
  };
})();