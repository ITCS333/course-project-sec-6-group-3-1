/*
  Requirement: Populate the "Weekly Course Breakdown" list page.
  Instructions:
  1. This file is already linked to `list.html` via:
         <script src="list.js" defer></script>
  2. In `list.html`, the <section id="week-list-section"> is the container
     that this script populates.
  3. Implement the TODOs below.
*/

// --- Element Selections ---
// TODO: Select the section for the week list using its id 'week-list-section'.
const weekListSection = document.getElementById("week-list-section");

// --- Functions ---

/**
 * TODO: Implement createWeekArticle.
 *
 * Parameters:
 *   week — one object from the API response with the shape:
 *     {
 *       id:          number,   // integer primary key from the weeks table
 *       title:       string,
 *       start_date:  string,   // "YYYY-MM-DD" — matches the SQL column name
 *       description: string,
 *       links:       string[]  // already decoded array of URL strings
 *     }
 *
 * Returns:
 *   An <article> element matching the structure shown in list.html:
 *     <article>
 *       <h2>{title}</h2>
 *       <p>Starts on: {start_date}</p>
 *       <p>{description}</p>
 *       <a href="details.html?id={id}">View Details & Discussion</a>
 *     </article>
 *
 * Important: the href MUST be "details.html?id=<id>" (integer id from
 * the weeks table) so that details.js can read the id from the URL.
 */
function createWeekArticle(week) {
  const article = document.createElement("article");

  const h2 = document.createElement("h2");
  h2.textContent = week.title;

  const startDateP = document.createElement("p");
  startDateP.textContent = "Starts on: " + week.start_date;

  const descriptionP = document.createElement("p");
  descriptionP.textContent = week.description;

  const link = document.createElement("a");
  link.href = "details.html?id=" + week.id;
  link.textContent = "View Details & Discussion";

  article.appendChild(h2);
  article.appendChild(startDateP);
  article.appendChild(descriptionP);
  article.appendChild(link);

  return article;
}

/**
 * TODO: Implement loadWeeks (async).
 *
 * It should:
 * 1. Use fetch() to GET data from './api/index.php'.
 *    The API returns JSON in the shape:
 *      { success: true, data: [ ...week objects ] }
 * 2. Parse the JSON response.
 * 3. Clear any existing content from the list section.
 * 4. Loop through the data array. For each week object:
 *    - Call createWeekArticle(week).
 *    - Append the returned <article> to the list section.
 */
async function loadWeeks() {
  try {
    const response = await fetch("./api/index.php");

    if (!response.ok) {
      console.error("Failed to fetch weeks:", response.statusText);
      alert("Failed to load weeks from the server.");
      return;
    }

    const result = await response.json();

    weekListSection.innerHTML = "";

    result.data.forEach(function(week) {
      const article = createWeekArticle(week);
      weekListSection.appendChild(article);
    });
  } catch (error) {
    console.error("Error:", error);
    alert("An error occurred while loading weeks: " + error.message);
  }
}

// --- Initial Page Load ---
loadWeeks();
