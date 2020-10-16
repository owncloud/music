/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2020 Pauli Järvinen
 *
 */

const path = require('path');
const webpack = require("webpack");
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const ESLintPlugin = require('eslint-webpack-plugin');

module.exports = {
  mode: 'production',
  devtool: 'source-map',
  entry: {
    app: '../js/config/index.js',
    files_music_player: '../js/embedded/index.js'
  },
  output: {
    filename: 'webpack.[name].js',
    path: path.resolve(__dirname, '../js/public'),
  },
  resolve: {
    alias: {
      'node_modules': path.resolve(__dirname, 'node_modules'),
      'vendor': path.resolve(__dirname, '../js/vendor'),
      'angular': path.resolve('node_modules', 'angular'),
      'lodash': path.resolve('node_modules', 'lodash'),
      'jquery': path.resolve('node_modules', 'jquery/src/jquery'),
    }
  },
  plugins: [
    new MiniCssExtractPlugin({filename: 'webpack.app.css'}),
    new ESLintPlugin({files: '../js'}),
    new webpack.ProvidePlugin({
      '$': 'jquery',
      'window.$': 'jquery',
      'jQuery': 'jquery',
      'window.jQuery': 'jquery',
      '_': 'lodash'
    })
  ],
  module: {
    rules: [
      {
        test: /\.css$/,
        use: [
          MiniCssExtractPlugin.loader,
          'css-loader',
        ],
      },
      {
        test: /\.(png|svg|jpg|gif)$/,
        use: [
          {
            loader: 'file-loader',
            options: {
              outputPath: 'img/'
            }
          }
        ],
      },
      {
        include: path.resolve('node_modules', 'lodash'),
        parser: { amd: false }
      }
    ],
  },
};