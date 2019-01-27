const sqlite3 = require("sqlite3").verbose();

class AppDAO {
  constructor(dbFilePath) {
    this.db = new sqlite3.Database(dbFilePath, err => {
      if (err) {
        console.log("Could not connect to database", err);
      } else {
        console.log("Connected to database");
      }
    });
  }

  getItems() {
    const sql = "SELECT rowid AS id, info FROM lorem";
    dao.db.all(sql, function(err, rows) {
      console.log(
        "%c sqlite3 ::: results are...",
        "background: red; color: white; font-weight: bold; font-size: 14px;",
        rows
      );
    });
  }

  close() {
    this.db.close();
  }
}

const dao = new AppDAO("./database/sqlite/database.sqlite3");
dao.getItems();
dao.close();
