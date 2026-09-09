import { defineConfig } from "vite";
import preact from "@preact/preset-vite";

// Build a librería única (IIFE, sin hashing de nombre de archivo) que el
// plugin PHP encola con wp_enqueue_script — WordPress no sabe nada de
// manifests de Vite ni de módulos ES nativos en el admin, así que el
// output tiene que ser un script clásico auto-ejecutable. outDir apunta
// DENTRO del árbol PHP del tema (../inc/build) para que Sofia_Panel_Editor
// (PHP) sirva el archivo compilado sin necesitar copiarlo a mano.
export default defineConfig({
  plugins: [preact()],
  build: {
    outDir: "../inc/build",
    emptyOutDir: true,
    lib: {
      entry: "src/main.jsx",
      formats: ["iife"],
      name: "SofiaEditorAdmin",
      fileName: () => "editor-admin.js",
    },
    rollupOptions: {
      output: {
        // Un solo <link> de CSS con nombre fijo, mismo criterio que el JS
        // — sin hash, para que el enqueue de PHP no tenga que leer un
        // manifest.
        assetFileNames: "editor-admin.[ext]",
      },
    },
  },
});
