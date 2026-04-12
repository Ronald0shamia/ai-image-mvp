document.getElementById('ai-btn').addEventListener('click', function() {

    let file = document.getElementById('ai-image').files[0];

    if (!file) {
        alert("Please select an image");
        return;
    }

    let formData = new FormData();
    formData.append('action', 'ai_analyze');
    formData.append('image', file);

    let ads = document.getElementById('ai-ads');
    let result = document.getElementById('ai-result');

    // Show Ads
    ads.style.display = 'block';
    ads.innerHTML = AI_TOOL.ads_code || "<p>Loading...</p>";

    result.innerHTML = "";

    fetch(AI_TOOL.ajax_url, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {

        setTimeout(() => { // wichtig für Ads Anzeige
            ads.style.display = 'none';
            result.innerHTML = data.data;
        }, 2000);

    })
    .catch(() => {
        ads.style.display = 'none';
        result.innerHTML = "Error occurred";
    });

});