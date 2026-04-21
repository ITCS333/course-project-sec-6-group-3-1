/*
  Requirement: Make the "Manage Weekly Breakdown" page interactive.

  Instructions:
  1. This file is already linked to `admin.html` via:
         <script src="admin.js" defer></script>

  2. In `admin.html`:
     - The form has id="week-form".
     - The submit button has id="add-week".
     - The <tbody> has id="weeks-tbody".
     - Columns rendered per row: Week Title | Start Date | Description | Actions.

  3. Implement the TODOs below.

  API base URL: ./api/index.php
  All requests and responses use JSON.
  Successful list response shape: { success: true, data: [ ...week objects ] }
  Each week object shape:
    {
      id:          number,   // integer primary key from the weeks table
      title:       string,
      start_date:  string,   // "YYYY-MM-DD"
      description: string,
      links:       string[]  // decoded array of URL strings
    }
*/

// --- Global Data Store ---
// Holds the weeks currently displayed in the table.
let weeks = [];

// --- Element Selections ---
// TODO: Select the week form by id 'week-form'.
const weekForm = document.getElementById("week-form");

// TODO: Select the weeks table body by id 'weeks-tbody'.
const weeksTbody = document.getElementById("weeks-tbody");

// --- Functions ---

/**
 * TODO: Implement createWeekRow.
 *
 * Parameters:
 *   week — one week object with shape:
 *     { id, title, start_date, description, links }
 *
 * Returns a <tr> element with four <td>s:
 *   1. title
 *   2. start_date  (the "YYYY-MM-DD" string from the weeks table)
 *   3. description
 *   4. Actions — two buttons:
 *        <button class="edit-btn"   data-id="{id}">Edit</button>
 *        <button class="delete-btn" data-id="{id}">Delete</button>
 *      The data-id holds the integer primary key from the weeks table.
 */
function createWeekRow(week) {
  const tr = document.createElement("tr");

  const titleTd = document.createElement("td");
  titleTd.textContent = week.title;

  const dateTd = document.createElement("td");
  dateTd.textContent = week.start_date;

  const descTd = document.createElement("td");
  descTd.textContent = week.description;

  const actionsTd = document.createElement("td");

  const editBtn = document.createElement("button");
  editBtn.textContent = "Edit";
  editBtn.className = "edit-btn";
  editBtn.dataset.id = week.id;

  const deleteBtn = document.createElement("button");
  deleteBtn.textContent = "Delete";
  deleteBtn.className = "delete-btn";
  deleteBtn.dataset.id = week.id;

  actionsTd.appendChild(editBtn);
  actionsTd.appendChild(deleteBtn);

  tr.appendChild(titleTd);
  tr.appendChild(dateTd);
  tr.appendChild(descTd);
  tr.appendChild(actionsTd);

  return tr;
}

/**
 * TODO: Implement renderTable.
 *
 * It should:
 * 1. Clear the weeks table body (set innerHTML to "").
 * 2. Loop through the global `weeks` array.
 * 3. For each week, call createWeekRow(week) and append the <tr>
 *    to the table body.
 */
function renderTable() {
  weeksTbody.innerHTML = "";

  weeks.forEach(function(week) {
    const row = createWeekRow(week);
    weeksTbody.appendChild(row);
  });
}

/**
 * TODO: Implement handleAddWeek (async).
 *
 * This is the event handler for the form's 'submit' event.
 * It should:
 * 1. Call event.preventDefault().
 * 2. Read values from:
 *      - #week-title       → title (string)
 *      - #week-start-date  → start_date (string, "YYYY-MM-DD")
 *      - #week-description → description (string)
 *      - #week-links       → split by newlines (\n) and filter empty
 *                            strings to produce a links array.
 * 3. Check if the submit button (#add-week) has a data-edit-id attribute.
 *    - If it does, call handleUpdateWeek() with that id and the field values.
 *    - If it does not, send a POST to './api/index.php' with the body:
 *        { title, start_date, description, links }
 *      On success (result.success === true):
 *        - Add the new week (with the id from result.id) to the global
 *          `weeks` array.
 *        - Call renderTable().
 *        - Reset the form.
 */
async function handleAddWeek(event) {
  event.preventDefault();

  const title = document.getElementById("week-title").value.trim();
  const start_date = document.getElementById("week-start-date").value;
  const description = document.getElementById("week-description").value.trim();
  const links = document.getElementById("week-links").value
    .split("\n")
    .filter(function(line) { return line.trim() !== ""; });

  const addBtn = document.getElementById("add-week");
  const editId = addBtn.dataset.editId;

  if (editId) {
    await handleUpdateWeek(parseInt(editId), { title, start_date, description, links });
  } else {
    try {
      const response = await fetch("./api/index.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ title, start_date, description, links })
      });

      const result = await response.json();

      if (result.success) {
        weeks.push({
          id: result.id,
          title,
          start_date,
          description,
          links
        });
        renderTable();
        weekForm.reset();
      } else {
        alert(result.message || "An error occurred.");
      }
    } catch (error) {
      alert("An error occurred: " + error.message);
    }
  }
}

/**
 * TODO: Implement handleUpdateWeek (async).
 *
 * Parameters:
 *   id     — the integer primary key of the week being edited.
 *   fields — object with { title, start_date, description, links }.
 *
 * It should:
 * 1. Send a PUT to './api/index.php' with the body:
 *      { id, title, start_date, description, links }
 * 2. On success:
 *    - Update the matching entry in the global `weeks` array.
 *    - Call renderTable().
 *    - Reset the form.
 *    - Restore the submit button text to "Add Week" and remove
 *      its data-edit-id attribute.
 */
async function handleUpdateWeek(id, fields) {
  try {
    const response = await fetch("./api/index.php", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id, ...fields })
    });

    const result = await response.json();

    if (result.success) {
      weeks = weeks.map(function(week) {
        if (week.id === id) {
          return { id, ...fields };
        }
        return week;
      });

      renderTable();
      weekForm.reset();

      const addBtn = document.getElementById("add-week");
      addBtn.textContent = "Add Week";
      delete addBtn.dataset.editId;
    } else {
      alert(result.message || "An error occurred.");
    }
  } catch (error) {
    alert("An error occurred: " + error.message);
  }
}

/**
 * TODO: Implement handleTableClick (async).
 *
 * This is a delegated click listener on the weeks table body.
 * It should:
 * 1. If event.target has class "delete-btn":
 *    a. Read the integer id from event.target.dataset.id.
 *    b. Send a DELETE to './api/index.php?id=<id>'.
 *    c. On success, remove the week from the global `weeks` array
 *       and call renderTable().
 *
 * 2. If event.target has class "edit-btn":
 *    a. Read the integer id from event.target.dataset.id.
 *    b. Find the matching week in the global `weeks` array.
 *    c. Populate the form fields (#week-title, #week-start-date,
 *       #week-description, #week-links) with the week's data.
 *       For #week-links, join the links array with newlines (\n).
 *    d. Change the submit button (#add-week) text to "Update Week"
 *       and set its data-edit-id attribute to the week's id.
 */
async function handleTableClick(event) {
  const target = event.target;

  if (target.classList.contains("delete-btn")) {
    const id = parseInt(target.dataset.id);

    try {
      const response = await fetch("./api/index.php?id=" + id, {
        method: "DELETE"
      });

      const result = await response.json();

      if (result.success) {
        weeks = weeks.filter(function(week) {
          return week.id !== id;
        });
        renderTable();
      } else {
        alert(result.message || "An error occurred.");
      }
    } catch (error) {
      alert("An error occurred: " + error.message);
    }
  }

  if (target.classList.contains("edit-btn")) {
    const id = parseInt(target.dataset.id);

    const week = weeks.find(function(w) {
      return w.id === id;
    });

    if (!week) return;

    document.getElementById("week-title").value = week.title;
    document.getElementById("week-start-date").value = week.start_date;
    document.getElementById("week-description").value = week.description;
    document.getElementById("week-links").value = week.links.join("\n");

    const addBtn = document.getElementById("add-week");
    addBtn.textContent = "Update Week";
    addBtn.dataset.editId = week.id;
  }
}

/**
 * TODO: Implement loadAndInitialize (async).
 *
 * It should:
 * 1. Send a GET to './api/index.php'.
 *    Response shape: { success: true, data: [ ...week objects ] }
 * 2. Store the data array in the global `weeks` variable.
 * 3. Call renderTable() to populate the table.
 * 4. Attach the 'submit' event listener to the week form
 *    (calls handleAddWeek).
 * 5. Attach a 'click' event listener to the weeks table body
 *    (calls handleTableClick — event delegation for edit and delete).
 */
async function loadAndInitialize() {
  try {
    const response = await fetch("./api/index.php");

    if (!response.ok) {
      console.error("Failed to fetch weeks:", response.statusText);
      alert("Failed to load weeks from the server.");
      return;
    }

    const result = await response.json();
    weeks = result.data;
    renderTable();

    weekForm.addEventListener("submit", handleAddWeek);
    weeksTbody.addEventListener("click", handleTableClick);
  } catch (error) {
    console.error("Error:", error);
    alert("An error occurred while loading weeks: " + error.message);
  }
}

// --- Initial Page Load ---
loadAndInitialize();
