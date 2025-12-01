// -------------------------------------------------------
// AUTOCOMPLETE – jen FETCHEM
// -------------------------------------------------------
async function load_data(query) {

    if (query.length <= 2) {
        document.getElementById('search_result').innerHTML = '';
        return;
    }

    const formData = new FormData();
    formData.append('query', query);

    try {
        const response = await fetch(
            "index.php?option=com_ajax&module=virtuemart_zsearchsphinx&format=raw",
            {
                method: "POST",
                body: formData
            }
        );

        if (!response.ok) {
            throw new Error("Chyba při načítání dat.");
        }

        const data = await response.json();

        let html = '<div class="list-group">';

        if (data.length > 0) {
            data.forEach(row => {
                html += `<a href="#" class="list-group-item list-group-item-action">${row.product_name}</a>`;
            });
        } else {
            html += '<a href="#" class="list-group-item list-group-item-action disabled">No Data Found</a>';
        }

        html += '</div>';

        document.getElementById('search_result').innerHTML = html;

    } catch (err) {
        console.error("Autocomplete chyba:", err);
    }
}


// -------------------------------------------------------
// KLIKNUTÍ NA POLOŽKU – ULOŽIT RECENT SEARCH
// -------------------------------------------------------
async function get_text(el) {

    let string = el.textContent.trim();
    console.log("Zvolený text:", string);

    // Odeslat recent search
    try {
        const response = await fetch(
            "index.php?option=com_ajax&module=virtuemart_zsearchsphinx&format=raw",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({ search_query: string })
            }
        );

        const data = await response.json();
        console.log("Recent search odpověď:", data);

    } catch (err) {
        console.error("Chyba při odeslání recent search:", err);
    }

    // Nastavit input a zavřít list
    document.getElementById('search_box').value = string;
    document.getElementById('search_result').innerHTML = '';
}



// -------------------------------------------------------
// EVENTY
// -------------------------------------------------------
document.getElementById("search_box").addEventListener("keyup", function () {
    load_data(this.value);
});

document.getElementById('search_result').addEventListener('click', function (event) {
    if (event.target && event.target.matches('a.list-group-item')) {
        event.preventDefault();
        get_text(event.target);
    }
});
