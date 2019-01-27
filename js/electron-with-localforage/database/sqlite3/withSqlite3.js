const sqlite3 = require("sqlite3");

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
}

const dao = new AppDAO("./database/sqlite/database.sqlite3");
console.log(dao);
console.dir(sqlite3)
console.log(dao.db)

module.exports = AppDAO;
