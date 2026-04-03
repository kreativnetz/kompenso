<?php require_once('../includes/initialize.php'); ?>
<? if (!$session->is_logged_in(2)) redirect_to('index.php'); ?>

<style type="text/css">
  body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
    background-color: #fff;
    padding: 0;
  }

  #container {
    width: 18cm;
    margin: 20px auto;
  }

  #mutterschiff {
    display: flex;
    flex-wrap: wrap;
  }

  #left {
    flex: 2;
    padding: 20px;
  }

  #right {
    flex: 1;
    padding: 20px;
  }

  .table-container {
    overflow-x: auto;
    width: 100%;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
    font-size: 9.5pt;
  }

  th, td {
    text-align: left;
    padding: 12px;
    border: 1px solid #ddd;
    vertical-align: top;
  }

  th {
    background-color: #f2f2f2;
  }

  tr:nth-child(even) {
    background-color: #f9f9f9;
  }

  tr:hover {
    background-color: #f1f1f1;
  }

@media print { 
  .noprint {
    display: none; 
  }
  tr {
    page-break-inside: avoid;
  }  
}


</style>

<body>
<div id="container">

  <?
  if (isset($_GET['year'])) {
    $this_year = $_GET['year'];
  } else {
    $this_year = $year_key;
  }
  ?>

  <select name="year" class="noprint" onchange="window.location = 'liste.php?year=' + this.value">
    <?
    foreach($years as $key => $year) {
      ?>
      <option value="<?=$key?>" <?=$key == $this_year ? "selected='selected'" : ""?>><?=$year?></option>
      <?
    } 
    ?>
  </select>


  <div class="table-container">
    <h3>Betreuungsliste <?=$years[$this_year]?></h3>
    <table id="thesisTable" width="100%">
      <thead>
        <tr>
          <th onclick="sortTable(0)" style="cursor:pointer;" width="45%">Titel der Arbeit</th>
          <th onclick="sortTable(1)" style="cursor:pointer;" >Autor/in</th>
          <th onclick="sortTable(2)" style="cursor:pointer;">HB</th>
          <th onclick="sortTable(3)" style="cursor:pointer;">GB</th>
        </tr>
      </thead>
      <tbody>
        <?

        $sql = "SELECT t.title, 
                       GROUP_CONCAT(DISTINCT CONCAT(a.first_name, ' ', a.last_name, ' (', a.class, ')') ORDER BY a.last_name, a.first_name SEPARATOR '<br />') AS authors, 
                       MAX(CASE WHEN s.type = 1 THEN tchr.token END) AS haupt, 
                       MAX(CASE WHEN s.type = 2 THEN tchr.token END) AS gegen
                FROM thesis AS t
                JOIN authors AS a ON t.id = a.thesis
                LEFT JOIN supervisions AS s ON t.id = s.thesis 
                LEFT JOIN teachers AS tchr ON s.teacher = tchr.id
                WHERE t.year = '".$years[$this_year]."'
                AND s.status = 1
                GROUP BY t.id, t.title
                ORDER BY haupt, gegen, t.title";

        $result_set = $db->query($sql);

        while ($row = $db->fetch_array($result_set)) {
          ?>
          <tr>
            <td><?=$row['title']?></td>
            <td><?=$row['authors']?></td>
            <td><?=$row['haupt']?></td>
            <td><?=$row['gegen']?></td>
          </tr>            
          <?
        }
        ?>        
      </tbody>
    </table>
  </div>

</div> <!-- container -->
</body>
</html>


<script>
// Simple sort function for table columns
function sortTable(colIndex) {
  const table = document.getElementById("thesisTable");
  const tbody = table.tBodies[0];
  const rows = Array.from(tbody.rows);

  // Detect current sort direction
  const dir = "asc";
  table.setAttribute("data-sort-dir-"+colIndex, dir);

  rows.sort((a, b) => {
    const valA = a.cells[colIndex].innerText.trim().toLowerCase();
    const valB = b.cells[colIndex].innerText.trim().toLowerCase();

    if (valA < valB) return dir === "asc" ? -1 : 1;
    if (valA > valB) return dir === "asc" ? 1 : -1;
    return 0;
  });

  rows.forEach(row => tbody.appendChild(row)); // re-append sorted rows
}
</script>

<?php if (isset($db)) { $db->close_connection(); } ?>
