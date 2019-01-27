const sqlite3 = require("sqlite3").verbose();
const db = new sqlite3.Database("./database/sqlite/database.sqlite3");

const sql = "SELECT rowid AS id, info FROM lorem";

db.all(sql, function(err, rows) {
  console.log(
    "%c using sqlite",
    "background: red; color: white;" + " font-weight: bold"
  );
  console.log(rows);
});

db.close();
