
function get_text(event) {
    let string = event.textContent;

    console.log("Zvolený text: " + string); // Debugging

    // Odesílání požadavku pomocí Fetch API
    fetch("index.php?option=com_ajax&module=virtuemart_zsearchsphinx&format=raw", {
        method: "POST",
        body: JSON.stringify({
            search_query: string
        }),
        headers: {
            "Content-type": "application/json; charset=UTF-8"
        }
    })
    .then(function(response) {
        if (!response.ok) {
            throw new Error("Chyba při načítání dat z API.");
        }
        return response.json();
    })
    .then(function(responseData) {
        console.log(responseData); // Debugging, co se vrací z API

        // Aktualizace hodnoty pole a výsledků
        document.getElementById('search_box').value = string;
        document.getElementById('search_result').innerHTML = ''; // Prázdný obsah

    })
    .catch(function(error) {
        console.error("Došlo k chybě při zpracování požadavku:", error);
    });
}



function load_data(query)
{
    if(query.length > 2)
    {
	let form_data = new FormData();
	form_data.append('query', query);
	let ajax_request = new XMLHttpRequest();
	ajax_request.open('POST', 'index.php?option=com_ajax&module=virtuemart_zsearchsphinx&format=raw', true);
        ajax_request.send(form_data);
	ajax_request.onreadystatechange = function()
	{
            if(ajax_request.readyState == 4 && ajax_request.status == 200)
            {
		let response = JSON.parse(ajax_request.responseText);
		let html = '<div class="list-group">';
		if(response.length > 0)
		{
                    for(let count = 0; count < response.length; count++)
                    {
                        const newLocal = '<a href="#" class="list-group-item list-group-item-action">';
                        html += newLocal+response[count].product_name+'</a>';
                    }
		}
		else
		{
                    html += '<a href="#" class="list-group-item list-group-item-action disabled">No Data Found</a>';
		}
		html += '</div>';
		document.getElementById('search_result').innerHTML = html;
            }
	};
    }
    else
    {
	document.getElementById('search_result').innerHTML = '';
    }
}
 // Add event listener to search box (keyup event)
document.getElementById("search_box").onkeyup = function() {load_data(this.value);};  

 // Event delegation for handling dynamically created items
document.getElementById('search_result').addEventListener('click', function(event) {
    if (event.target && event.target.matches('a.list-group-item.list-group-item-action')) {
        get_text(event.target); // Pass the clicked element to get_text function
        }
    });
