# Grid Layout Site

## CSS

```css
.container {
  display: grid;
  width: 1024px;
  height: 960px;
  grid-template-columns: 1fr 1fr 1fr;
  grid-template-rows: 200px 1fr 1fr 100px;
  grid-gap: 1rem;
}

.header {
  grid-row: 1 / 2;
  grid-column: 2 / 4;
}

.sidebar {
  grid-row: 1 / 4;
  grid-column: 1 / 2;
}

.content-1 {
  grid-row: 2 / 3;
  grid-column: 2 / 4;
}

.content-2 {
  grid-row: 3 / 4;
  grid-column: 2 / 3;
}

.content-3 {
  grid-row: 3 / 4;
  grid-column: 3 / 4;
}

.footer {
  grid-row: 4 / 5;
  grid-column: 1 / 4;
}

/* OTHER STYLES */

body {
  padding: 200px;
  background-color: #3b404e;
  display: flex;
  justify-content: center;
  padding: 20px;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
}

.item {
  background-color: #1EAAFC;
  background-image: linear-gradient(130deg, #6C52D9 0%, #1EAAFC 85%, #3EDFD7 100%);
  box-shadow: 0 10px 20px rgba(0,0,0,0.19), 0 6px 6px rgba(0,0,0,0.23);
  color: #ffffff;
  border-radius: 4px;
  border: 6px solid #171717;
  display: flex;
  justify-content: center;
  align-items: center;
  font-size: 18px;
  font-weight: bold;
}

.header {
  background-color: #1EAAFC;
  background-image: linear-gradient(160deg, #6C52D9 0%, #9B8AE6 127%);
}

.sidebar {
  background-image: linear-gradient(203deg, #3EDFD7 0%, #29A49D 90%)
}

.content-1,
.content-2,
.content-3 {
  background-image: linear-gradient(130deg, #6C52D9 0%, #1EAAFC 85%, #3EDFD7 100%);
}

.footer {
  background-color: #6C52D9;
  background-image: linear-gradient(160deg, #6C52D9 0%, #9B8AE6 127%);
}
```

## HTML

``html
<div class="container">
  <div class="item header">header</div>
  <div class="item sidebar">sidebar</div>
  <div class="item content-1">Content-1</div>
  <div class="item content-2">Content-2</div>
  <div class="item content-3">Content-3</div>
  <div class="item footer">footer</div>
</div>
```
