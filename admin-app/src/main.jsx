import { render } from "preact";
import { App } from "./App.jsx";
import "./style.css";

// SofiaEditorConfig lo inyecta PHP vía wp_localize_script (ver
// inc/class-panel-editor.php) — nombre del sitio, slug de la página, la
// URL de la propia REST API de WordPress (sofia/v1) y el nonce de la REST
// API (wpApiSettings.nonce), nunca el AgenteToken: el navegador jamás debe
// verlo, solo el proxy PHP server-side lo usa.
const raiz = document.getElementById("sofia-editor-admin-root");
if (raiz) {
  render(<App config={window.SofiaEditorConfig} />, raiz);
}
