(function () {
    const container = document.getElementById('guru-feed-container');
    if (!container) {
        console.error('Guru SEO: Container #guru-feed-container not found.');
        return;
    }

    const projectId = container.getAttribute('data-project-id');
    if (!projectId) {
        console.error('Guru SEO: data-project-id attribute missing on container.');
        return;
    }

    // Determine API URL (assuming relative path for now, but in production this would be absolute)
    // For local dev, we might need a specific URL. Let's assume the script is loaded from the same domain or a known API endpoint.
    // If this script is hosted on the Laravel app, we can use the origin.
    // If hosted externally, we'd need the full URL.
    // Let's assume for this MVP we use a relative path if on same domain, or hardcode/configurable base URL.
    // For the sake of the task, let's assume the widget is being served FROM the Laravel app, or the user knows where to point it.
    // We will try to infer the base URL from the script src, or default to relative.

    // Simple approach: Use a hardcoded base URL for local dev or relative if served from same origin.
    // Let's use a relative path '/api/feed/' + projectId assuming we are integrating this into a page served by this app or proxying.
    // BUT the requirement says "Headless" for "static sites", so it's likely cross-origin.
    // We should probably allow the user to specify the API base URL or default to where the script was loaded from.

    // Let's try to get the script source to find the base URL.
    const scripts = document.getElementsByTagName('script');
    let baseUrl = '';
    for (let script of scripts) {
        if (script.src.includes('guru-loader.js')) {
            const url = new URL(script.src);
            baseUrl = url.origin; // e.g., http://localhost:8000
            break;
        }
    }

    if (!baseUrl) {
        console.warn('Guru SEO: Could not determine base URL from script tag. defaulting to empty string (relative).');
    }

    const apiUrl = `${baseUrl}/api/feed/${projectId}`;

    fetch(apiUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(articles => {
            if (articles.length === 0) {
                container.innerHTML = '<p>No latest articles found.</p>';
                return;
            }

            let html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">';

            articles.forEach(article => {
                // Since we don't have a public URL for the article yet in the model, we'll placeholder it or link to #
                // In a real app, Article would have a 'slug' or 'url' attribute.
                // We'll use '#' for now as requested by the minimal prompt requirements.
                html += `
                    <div style="border: 1px solid #ddd; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                        <h3 style="margin-top: 0;">${article.title}</h3>
                        <p style="color: #666; font-size: 0.9em;">${new Date(article.created_at).toLocaleDateString()}</p>
                        <a href="#" style="text-decoration: none; color: #3490dc; font-weight: bold;">Read More &rarr;</a>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Guru SEO: Error fetching articles:', error);
            container.innerHTML = '<p>Error loading content.</p>';
        });
})();
