<?php
// version 13 April 2023 23:06 working + improvements
//Allow for testing
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);


if (isset($_POST['submit'])) {
    if (!wp_verify_nonce($_POST['recipe_generator_nonce'], 'recipe_generator')) {
        die('Invalid nonce value.');
    }


    // Sanitize user input
    $ingredients = sanitize_text_field($_POST['ingredients']);

    // Handle dietary preferences
    if (isset($_POST['dietary_preferences'])) {
        $sanitized_dietary_preferences = array_map('sanitize_text_field', $_POST['dietary_preferences']);
        $dietary_preferences = implode(', ', $sanitized_dietary_preferences);

        if (in_array('Custom', $sanitized_dietary_preferences) && isset($_POST['custom_dietary_pref'])) {
            $custom_dietary_pref = sanitize_text_field($_POST['custom_dietary_pref']);
            $dietary_preferences = str_replace('Custom', $custom_dietary_pref, $dietary_preferences);
        }
    } else {
        $dietary_preferences = '';
    }

    // Handle cuisine preferences
    $cuisine_preferences = sanitize_text_field($_POST['cuisine_preferences']);
    if ($cuisine_preferences === 'Custom' && isset($_POST['custom_cuisine'])) {
        $cuisine_preferences = sanitize_text_field($_POST['custom_cuisine']);
    }

    //Test what is entered in the forms
    //echo "Ingredients: " . $ingredients . "<br>";
    //echo "Dietary Preferences: " . $dietary_preferences . "<br>";
    //echo "Cuisine Preferences: " . $cuisine_preferences . "<br>";

    // OpenAI API request
    $api_key = getenv('OPENAI_API_KEY');
    $model = "gpt-3.5-turbo";
    $url = "https://api.openai.com/v1/chat/completions";
    $messages = [
        ["role" => "system", "content" => "You act as if you were Gordon Ramsay and you help users generate simple and fast to make recipes."],
        ["role" => "user", "content" => "Create 3 cuisine recipes based on the following ingredients, dietary preferences, and cuisine preferences: Ingredients: {$ingredients}, Dietary preferences: {$dietary_preferences}, Cuisine preferences: {$cuisine_preferences}. The recipes should be easy to follow with a section with list of ingredients and a section with instructions for cooking. Each recipe title should be in bold text and larger font size than the recipe text. Do not provide any of your comments or confirmations, just the recipes."]
    ];

    $headers = [
        'Content-Type: application/json',
        "Authorization: Bearer $api_key"
    ];

    $data = [
        'model' => $model,
        'messages' => $messages,
        'max_tokens' => 1500,
        'n' => 1,
        'stop' => null,
        'temperature' => 0.7
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = json_decode(curl_exec($ch), true);

    // Test API Output - comment the line above to make it work
    //$response = curl_exec($ch);
    //echo "API Response: " . $response . "<br>";
    //$result = json_decode($response, true);

    if (curl_errno($ch)) {
        echo 'Error: ' . curl_error($ch);
    } else {
        if (isset($result['choices'][0]['message']['content'])) {
            $recipes = nl2br(trim($result['choices'][0]['message']['content']));
        } else {
            $recipes = 'Sorry, we could not generate recipes based on your input. Please try again.';
        }
        
        
    }
    
    curl_close($ch);
    
    if (isset($result['choices'][0]['message']['content'])) {
        $recipes = nl2br(trim($result['choices'][0]['message']['content']));
    } else {
        $recipes = 'Sorry, we could not generate recipes based on your input. Please try again.';
    }
    
}
?>


<style>
    form {
        display: flex;
        flex-direction: column;
        max-width: 400px;
        margin-bottom: 20px;
    }
    label {
        margin-top: 10px;
        margin-bottom: 5px;
    }
    input[type="text"],
    select {
        padding: 10px;
        font-size: 18px;
        margin-bottom: 40px;
    }
    input[type="text"]:focus,
    select:focus {
        border: 1px solid #999;
        background-color: #fff;
        outline: none;
    }
    .hidden {
        display: none;
    }
    .checkboxes {
        display: flex;
        flex-direction: column;
        margin-bottom: 10px;
    }
    .checkbox-container {
        display: flex;
        align-items: center;
        margin-bottom: 12px;
    }
    .checkbox-container input[type="checkbox"] {
        margin-top: 0;
        margin-right: 12px;
    }
    .checkbox-container label {
        margin-top: 0;
        margin-bottom: 0;
        line-height: 1.2;
    }
    button {
        margin-top: 10px;
        padding: 10px;
        text-align: center;
        text-decoration: none;
        display: inline-block;
        font-size: 14px;
        cursor: pointer;
        transition-duration: 0.4s;
    }

    .recipes {
        padding: 20px;
        background-color: #f1f1f1;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .processing-message {
        padding: 20px;
        background-color: #f1f1f1;
        border: 1px solid #ddd;
        border-radius: 4px;
        text-align: center;
    }

    @media (max-width: 480px) {
        form {
            max-width: 100%;
            margin-left: auto;
            margin-right: auto;
        }
    }
</style>







<!-- HTML code -->
<form method="post" action="#recipes-output" onsubmit="showProcessingMessage()">

    <?php wp_nonce_field('recipe_generator', 'recipe_generator_nonce'); ?>

    <h2>Ingredients</h2>
    <label for="ingredients">Ingredients:</label>
    <input type="text" id="ingredients" name="ingredients" value="<?php echo isset($ingredients) ? $ingredients : ''; ?>" required>

  
    <h2>Dietary Preferences</h2>
    <div class="checkboxes">
        <?php
        $dietary_options = [
            'Dairy-free', 'Diabetes', 'Gluten-free', 'Halal', 'Keto', 'Kosher',
            'Lactose-free', 'Low carb', 'Vegan', 'Vegetarian', 'Custom'
        ];
        foreach ($dietary_options as $option) {
            $id = strtolower(str_replace(' ', '_', $option));
            echo '<div class="checkbox-container">';
            echo "<input type='checkbox' id='{$id}' name='dietary_preferences[]' value='{$option}'>";
            echo "<label for='{$id}'>{$option}</label>";
            echo '</div>';
        }
        ?>
        <div class="hidden" id="custom_dietary_pref_container">
            <label for="custom_dietary_pref">Custom Dietary Preference:</label>
            <input type="text" id="custom_dietary_pref" name="custom_dietary_pref" placeholder="Enter your preference" disabled>
        </div>
    </div>

    <h2>Cuisine Preferences</h2>
    <label for="cuisine_preferences">Cuisine Preferences:</label>
    <select id="cuisine_preferences" name="cuisine_preferences" required>
        <option value="Italian">Italian</option>
        <option value="Chinese">Chinese</option>
        <option value="French">French</option>
        <option value="Spanish">Spanish</option>
        <option value="Japanese">Japanese</option>
        <option value="Indian">Indian</option>
        <option value="Greek">Greek</option>
        <option value="Thai">Thai</option>
        <option value="Mexican">Mexican</option>
        <option value="US">US</option>
        <option value="Custom">Custom</option>
    </select>
    <div class="hidden" id="custom_cuisine_container">
        <label for="custom_cuisine">Custom Cuisine:</label>
        <input type="text" id="custom_cuisine" name="custom_cuisine" placeholder="Enter your cuisine" disabled>
    </div>

    <button type="submit" name="submit">Generate Recipes</button>
</form>


<div class="processing-message" style="display: none;">
    <p>Please wait, your request is being processed. This usually takes around 15 seconds.</p>
</div>




<script>
// Handle custom dietary preference checkbox
const customDietaryPrefCheckbox = document.getElementById('custom');
const customDietaryPrefInput = document.getElementById('custom_dietary_pref');
const customDietaryPrefContainer = document.getElementById('custom_dietary_pref_container');

customDietaryPrefCheckbox.addEventListener('change', function () {
    if (this.checked) {
        customDietaryPrefInput.disabled = false;
        customDietaryPrefInput.required = true;
        customDietaryPrefContainer.classList.remove('hidden');
    } else {
        customDietaryPrefInput.disabled = true;
        customDietaryPrefInput.required = false;
        customDietaryPrefContainer.classList.add('hidden');
    }
});

// Handle custom cuisine preference dropdown
const cuisinePreferencesDropdown = document.getElementById('cuisine_preferences');
const customCuisineInput = document.getElementById('custom_cuisine');
const customCuisineContainer = document.getElementById('custom_cuisine_container');

cuisinePreferencesDropdown.addEventListener('change', function () {
    if (this.value === 'Custom') {
        customCuisineInput.disabled = false;
        customCuisineInput.required = true;
        customCuisineContainer.classList.remove('hidden');
    } else {
        customCuisineInput.disabled = true;
        customCuisineInput.required = false;
        customCuisineContainer.classList.add('hidden');
    }
});

function showProcessingMessage() {
        const processingMessage = document.querySelector('.processing-message');
        processingMessage.style.display = 'block';
    }

    document.querySelector('form').addEventListener('submit', showProcessingMessage);


</script>


<?php if (isset($recipes)): ?>
    <div class="recipes" id="recipes-output">
        <?php echo $recipes; ?>
    </div>
<?php endif; ?>

