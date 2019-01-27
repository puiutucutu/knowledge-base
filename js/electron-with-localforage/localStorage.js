const localforage = require("localforage");

const theStore = localforage.createInstance({
  name: "recipes"
});

theStore.setItem("key", "value");

theStore
  .setItem("123", {
    name: "Soup",
    ingredients: ["water", "chicken"]
  })
  .then(async function(value) {
    console.log("then... ", value);

    const retrieved = await theStore.getItem("123");
    console.log("retrieved: ", retrieved);
  });
