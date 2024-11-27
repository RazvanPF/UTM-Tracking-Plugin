(function () {
    const utmParams = utmTrackerParams; // Retrieved from WordPress options

function captureUtmParameters() {
    const urlParams = new URLSearchParams(window.location.search);

	// Log all URL parameters
    //console.log("URLSearchParams:", Object.fromEntries(urlParams.entries()));

    utmParams.forEach(param => {
        try {
            const value = urlParams.get(param);
            //console.log(`Checking param: ${param}, Value: ${value}`);
            if (value) {
                localStorage.setItem(param.toLowerCase(), value);
                //console.log(`Stored UTM: ${param} = ${value}`);
            }
        } catch (error) {
            console.error(`Error processing UTM parameter: ${param}`, error);
        }
    });
}

    function appendUtmToLinks() {
        document.querySelectorAll('a').forEach(link => {
            try {
                const href = link.getAttribute('href');

                // Skip links without href or with special attributes
                if (!href || href.startsWith('#') || href.startsWith('javascript:')) {
                    return;
                }

                // Ensure the link is internal
                const url = new URL(link.href, window.location.origin);
                if (url.hostname !== window.location.hostname) return;

                // Retrieve stored UTM parameters and append them
                utmParams.forEach(param => {
                    const value = localStorage.getItem(param.toLowerCase());
                    if (value) {
                        url.searchParams.set(param.toLowerCase(), value);
                    }
                });

                // Update the link href
                link.href = url.toString();
            } catch (error) {
                console.warn(`Skipping invalid URL for link: ${link.href}`, error);
            }
        });
    }

    function appendUtmToCurrentUrl() {
        const currentUrl = new URL(window.location.href);
        let updated = false;

        utmParams.forEach(param => {
            const value = localStorage.getItem(param.toLowerCase());
            if (value && !currentUrl.searchParams.has(param.toLowerCase())) {
                currentUrl.searchParams.set(param.toLowerCase(), value);
                updated = true;
            }
        });

        if (updated) {
            window.history.replaceState({}, '', currentUrl.toString());
            console.log(`Updated current URL: ${currentUrl.toString()}`);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        captureUtmParameters();
        appendUtmToLinks();
        appendUtmToCurrentUrl();
    });
})();
