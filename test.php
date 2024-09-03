<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Auto-Suggestions</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 20px;
    }

    label {
      font-weight: bold;
      margin-bottom: 5px;
      display: block;
    }

    .autocomplete-container {
      position: relative;
      display: inline-block;
    }

    input[type="text"] {
      padding: 5px;
      width: 220px;
      border: 1px solid #ccc;
      border-radius: 4px;
    }

    .suggestions {
      position: absolute;
      border: 1px solid #ccc;
      border-radius: 4px;
      background: white;
      width: 220px;
      z-index: 1000;
      max-height: 200px;
      overflow-y: auto;
    }

    .suggestion-item {
      padding: 5px;
      cursor: pointer;
    }

    .suggestion-item:hover {
      background: #f0f0f0;
    }
  </style>
</head>

<body>
  <div class="autocomplete-container">
    <label for="comboInput">Select or Enter Value:</label>
    <input type="text" id="comboInput" name="comboInput" placeholder="Enter value or select from list">
    <div id="suggestions" class="suggestions"></div>
  </div>

  <script>
    let debounceTimeout;

    document.getElementById('comboInput').addEventListener('input', function() {
      clearTimeout(debounceTimeout);

      const query = this.value;

      // Check if the input is empty, and hide suggestions if it is
      if (query === '') {
        document.getElementById('suggestions').innerHTML = '';
        document.getElementById('suggestions').style.display = 'none';
        return;
      }

      debounceTimeout = setTimeout(() => {
        fetch('fetch_requestors.php?query=' + encodeURIComponent(query))
          .then(response => {
            if (!response.ok) {
              throw new Error('Network response was not ok');
            }
            return response.json();
          })
          .then(requestors => {
            // Debug: Check the response in the console
            console.log('Requestors:', requestors);

            const suggestionsDiv = document.getElementById('suggestions');
            suggestionsDiv.innerHTML = '';

            if (requestors.length > 0) {
              suggestionsDiv.style.display = 'block';
              requestors.forEach(requestor => {
                const item = document.createElement('div');
                item.className = 'suggestion-item';
                item.textContent = requestor;
                item.onclick = function() {
                  document.getElementById('comboInput').value = requestor;
                  suggestionsDiv.innerHTML = '';
                  suggestionsDiv.style.display = 'none';
                };
                suggestionsDiv.appendChild(item);
              });
            } else {
              suggestionsDiv.style.display = 'none';
            }
          })
          .catch(error => console.error('Fetch error:', error));
      }, 300); // Debounce delay in milliseconds
    });

    // Hide suggestions when clicking outside
    document.addEventListener('click', function(event) {
      if (!event.target.closest('.autocomplete-container')) {
        document.getElementById('suggestions').style.display = 'none';
      }
    });
  </script>
</body>

</html>