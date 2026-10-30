import globals from "globals";
import eslintReact from "@eslint-react/eslint-plugin";
import pluginImport from "eslint-plugin-import-x";
import pluginUnicorn from "eslint-plugin-unicorn";
import pluginReactHooks from "eslint-plugin-react-hooks";
import tsParser from "@typescript-eslint/parser";
import tsPlugin from "@typescript-eslint/eslint-plugin";
import configPrettier from "eslint-config-prettier";

const reactRecommended = eslintReact.configs["recommended-typescript"];

export default [
  {
    ignores: [
      "**/node_modules/**",
      "**/public/**",
      "**/vendor/**",
      "**/storage/**",
      "**/dist/**",
      "**/*.min.js",
      "**/scripts/**",
    ],
  },
  pluginImport.flatConfigs.recommended,
  pluginImport.flatConfigs.typescript,
  {
    settings: {
      "import-x/resolver": {
        typescript: {
          alwaysTryTypes: true,
          project: "./tsconfig.json",
        },
      },
    },
    rules: {
      "import-x/extensions": "off",
    },
  },
  {
    files: ["**/__tests__/**"],
    rules: {
      "import-x/no-unresolved": "off",
    },
  },
  {
    files: ["**/*.tsx"],
    ...pluginImport.flatConfigs.react,
  },
  {
    ...pluginUnicorn.configs.recommended,
    rules: {
      ...pluginUnicorn.configs.recommended.rules,
      "unicorn/prevent-abbreviations": "off",
      "unicorn/no-null": "off",
      "unicorn/filename-case": "off",
      "unicorn/no-array-reduce": "off",
      "unicorn/no-array-for-each": "off",
      "unicorn/consistent-function-scoping": "off",
      "unicorn/no-negated-condition": "off",
      "unicorn/prefer-logical-operator-over-ternary": "off",
      "unicorn/prefer-set-has": "warn",
      "unicorn/prefer-string-slice": "warn",
      "unicorn/prefer-add-event-listener": "warn",
      "unicorn/no-array-callback-reference": "off",
      "unicorn/no-document-cookie": "warn",
      "unicorn/prefer-dom-node-append": "off",
      "unicorn/prefer-dom-node-remove": "off",
      "unicorn/prefer-global-this": "warn",
      "unicorn/name-replacements": "off",
      "unicorn/consistent-boolean-name": "off",
      "unicorn/consistent-class-member-order": "warn",
    },
  },
  {
    files: ["**/*.{ts,tsx}"],
    ...reactRecommended,
    languageOptions: {
      parser: tsParser,
      parserOptions: {
        ecmaVersion: "latest",
        sourceType: "module",
        ecmaFeatures: {
          jsx: true,
        },
      },
      globals: {
        ...globals.browser,
      },
    },
    plugins: {
      ...reactRecommended.plugins,
      "react-hooks": pluginReactHooks,
    },
    rules: {
      ...reactRecommended.rules,
      ...pluginReactHooks.configs.recommended.rules,
      "@eslint-react/exhaustive-deps": "off",
      "@eslint-react/rules-of-hooks": "off",
      "@eslint-react/set-state-in-effect": "off",
      "@eslint-react/use-memo": "off",
      "@eslint-react/no-array-index-key": "off",
      "@eslint-react/no-use-context": "off",
      "@eslint-react/naming-convention-ref-name": "off",
      "@eslint-react/use-state": "off",
      "@eslint-react/no-forward-ref": "off",
      "@eslint-react/dom-no-dangerously-set-innerhtml": "off",
      "@eslint-react/no-clone-element": "off",
      "@eslint-react/no-unnecessary-use-prefix": "off",
      "no-unused-vars": ["warn", { varsIgnorePattern: "^_", argsIgnorePattern: "^_" }],
    },
  },
  {
    files: ["**/*.js"],
    languageOptions: {
      globals: {
        ...globals.browser,
        ...globals.node,
      },
      ecmaVersion: "latest",
      sourceType: "module",
    },
  },
  {
    rules: {
      "no-unused-vars": ["warn", { varsIgnorePattern: "^_", argsIgnorePattern: "^_" }],
    },
  },
  {
    files: ["**/*.cjs"],
    languageOptions: {
      ecmaVersion: "latest",
      sourceType: "commonjs",
      globals: {
        ...globals.node,
      },
    },
    rules: {
      "unicorn/no-process-exit": "off",
      "unicorn/prefer-top-level-await": "off",
    },
  },
  {
    rules: {
      "import-x/namespace": "off",
      "import-x/default": "off",
      "import-x/no-named-as-default": "off",
      "import-x/no-named-as-default-member": "off",
    },
  },
  {
    plugins: {
      "@typescript-eslint": tsPlugin,
    },
    rules: {
      "@typescript-eslint/no-explicit-any": "warn",
      "@typescript-eslint/no-empty-object-type": "warn",
      "@typescript-eslint/no-require-imports": "off",
    },
  },
  // The e2e harness drives local sites (shop-laravel.local, 127.0.0.1) that
  // Local serves over plain HTTP, so the http URLs in its defaults and docs
  // are by design and eslint --fix must not rewrite them to https. Kept last
  // so it wins over the unicorn recommended set.
  {
    files: ["e2e/**", "playwright.config.ts"],
    rules: {
      "unicorn/prefer-https": "off",
    },
  },
  configPrettier,
];
