/**
 * Expo config plugin — fixe la version du Gradle Wrapper.
 *
 * EAS regenerates the android/ folder via `expo prebuild` à chaque build cloud,
 * donc on ne peut pas committer gradle-wrapper.properties directement (android/ est gitignored).
 * Ce plugin s'exécute pendant le prebuild et écrase la distributionUrl.
 */
const { withDangerousMod } = require('@expo/config-plugins');
const fs = require('fs');
const path = require('path');

const GRADLE_VERSION = '8.13';

module.exports = function withGradleWrapper(config) {
  return withDangerousMod(config, [
    'android',
    async (cfg) => {
      const wrapperPath = path.join(
        cfg.modRequest.platformProjectRoot,
        'gradle',
        'wrapper',
        'gradle-wrapper.properties',
      );

      if (!fs.existsSync(wrapperPath)) {
        console.warn('[withGradleWrapper] gradle-wrapper.properties introuvable, skip.');
        return cfg;
      }

      let contents = fs.readFileSync(wrapperPath, 'utf-8');

      // Remplace n'importe quelle version de Gradle par la version cible
      contents = contents.replace(
        /distributionUrl=https\\:\/\/services\.gradle\.org\/distributions\/gradle-[^-\n]+-bin\.zip/,
        `distributionUrl=https\\://services.gradle.org/distributions/gradle-${GRADLE_VERSION}-bin.zip`,
      );

      fs.writeFileSync(wrapperPath, contents, 'utf-8');
      console.log(`[withGradleWrapper] Gradle wrapper → ${GRADLE_VERSION}`);

      return cfg;
    },
  ]);
};
