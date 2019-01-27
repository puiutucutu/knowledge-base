const sqlite = require("sqlite");

const dbPromise = sqlite.open("./database/sqlite/database.sqlite3", {
  Promise
});

dbPromise.then(function(dbh) {
  Promise.all([
    dbh.get("SELECT rowid AS id, info FROM lorem"),
    dbh.all("SELECT rowid AS id, info FROM lorem")
  ]).then(function(results) {
    console.log(
      "%c sqlite ::: results are...",
      "background: red; color: white; font-weight: bold; font-size: 14px;",
      results
    );
  });
});
