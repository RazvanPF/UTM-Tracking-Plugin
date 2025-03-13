(function () {
    const utmParams = utmTrackerData.params || [];
    const sessionUtms = utmTrackerData.session_utms || {};
    const allowedHosts = utmTrackerData.allowed_hosts || [];
    const debugMode = utmTrackerData.debug || false; // Get debug setting
    const emailReplacements = utmTrackerData.email_replacements || []; // Get email rules
    const hideUTM = utmTrackerData.hide_utm || false; // Get hide UTM setting
	const bypassCache = utmTrackerData.bypass_cache || false;

	logDebug("📦 utmTrackerData received:", utmTrackerData);
	logDebug("📧 Email Replacements:", utmTrackerData.email_replacements);

    function logDebug(message, data = null) {
        if (debugMode) {
            console.log(message, data);
        }
    }

    function getHostname(urlString) {
        try {
            return new URL(urlString).hostname.replace(/^www\./, ''); // Normalize
        } catch (e) {
            logDebug(`🚨 Invalid URL detected: ${urlString}`);
            return '';
        }
    }

    function hasUTMParameters() {
        return Object.keys(sessionUtms).length > 0;
    }

	function removeUTMParams() {
		if (!bypassCache) return; // Only remove if bypass cache is enabled

		logDebug("🚀 Removing UTM parameters from URL...");

		const url = new URL(window.location.href);

		// Remove only UTM parameters, keeping other query parameters intact
		utmParams.forEach(param => url.searchParams.delete(param));

		// Update URL without reloading the page
		window.history.replaceState({}, document.title, url.toString());
	}

	function updateEmails() {
		if (!hasUTMParameters()) {
			logDebug("🔍 No UTM parameters found. Skipping email replacement.");
			return;
		}

		logDebug("🔄 Replacing emails based on UTM rules...");

		// **Sort email rules by length (longer ones first) to prevent overlap issues**
		emailReplacements.sort((a, b) => b.original.length - a.original.length);

		emailReplacements.forEach(rule => {
			let originalEmail = rule.original;
			let utmEmail = rule.replacement;

			if (!originalEmail || !utmEmail) return;

			// **Use word boundaries `\b` to prevent partial replacements**
			const regex = new RegExp(`\\b${originalEmail}\\b`, "g");

			// Find and replace email text in the document
			document.body.innerHTML = document.body.innerHTML.replace(regex, utmEmail);

			logDebug(`✅ Replaced '${originalEmail}' with '${utmEmail}'`);
		});
	}


    function updateLinks() {
        const currentHostname = getHostname(window.location.href);

        document.querySelectorAll('a').forEach(link => {
            try {
                const href = link.getAttribute('href');
                if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

                const url = new URL(href, window.location.origin);
                const targetHostname = getHostname(url.href);
                const isAllowed = allowedHosts.includes(targetHostname);
                const isInternal = targetHostname === currentHostname;

                logDebug(`🔍 Checking link: ${href} | Target Hostname: ${targetHostname} | Allowed: ${isAllowed} | Internal: ${isInternal}`);

                if (isInternal) {
                    let paramsAdded = false;
                    utmParams.forEach(param => {
                        if (sessionUtms[param]) {
                            url.searchParams.set(param, sessionUtms[param]);
                            paramsAdded = true;
                        }
                    });
                    if (paramsAdded) {
                        logDebug(`✅ Updated INTERNAL link with UTMs: ${url.toString()}`);
                        link.href = url.toString();
                    }
                    return;
                }

                if (!isAllowed) {
                    logDebug(`⛔ UTM parameters BLOCKED for EXTERNAL: ${targetHostname}, not in allowed hosts.`);
                    return;
                }

                link.addEventListener('click', function (event) {
                    event.preventDefault();
                    let paramsAdded = false;
                    utmParams.forEach(param => {
                        if (sessionUtms[param]) {
                            url.searchParams.set(param, sessionUtms[param]);
                            paramsAdded = true;
                        }
                    });

                    if (paramsAdded) {
                        logDebug(`✅ Redirecting to ALLOWED EXTERNAL site with UTM params: ${url.toString()}`);
                        window.open(url.toString(), '_blank');
                    } else {
                        logDebug("⚠️ No UTM params found, opening external link normally.");
                        window.open(href, '_blank');
                    }
                });

            } catch (error) {
                logDebug(`Skipping invalid URL for link: ${link.href}`, error);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        removeUTMParams(); // Remove UTM parameters if the setting is ON
        updateEmails(); // Replace emails on page load
        updateLinks(); // Update links
    });
})();