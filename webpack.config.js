/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2020 - 2023 Pauli Järvinen
 *
 */

const path = require('path');
const webpack = require("webpack");
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const ESLintPlugin = require('eslint-webpack-plugin');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const WebpackAssetsManifest = require('webpack-assets-manifest');

module.exports = {
  mode: 'production',
  devtool: 'source-map',
  entry: {
    app: './js/index.app.js',
    files_music_player: './js/index.embedded.js'
  },
  output: {
    filename: 'webpack.[name].[contenthash].js',
    path: path.resolve(__dirname, 'dist'),
  },
  resolve: {
    extensions: ['.tsx', '.ts', '.js'],
    alias: {
      'node_modules': path.resolve(__dirname, 'node_modules'),
      'vendor': path.resolve(__dirname, 'js/vendor'),
      'angular': path.resolve('node_modules', 'angular'),
      'lodash': path.resolve('node_modules', 'lodash'),
      'jquery': path.resolve('node_modules', 'jquery/src/jquery'),
      'blueimp-md5': path.resolve('node_modules', 'blueimp-md5'),
    }
  },
  plugins: [
    new CleanWebpackPlugin(),
    new MiniCssExtractPlugin({filename: 'webpack.[name].[contenthash].css'}),
    new ESLintPlugin({files: './js'}),
    new webpack.ProvidePlugin({
      '$': 'jquery',
      'window.$': 'jquery',
      'jQuery': 'jquery',
      'window.jQuery': 'jquery',
      '_': 'lodash',
      'window.AV': 'vendor/aurora/aurora.js',
      'Cookies': 'node_modules/js-cookie'
    }),
    new WebpackAssetsManifest(),
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
      },
      {
        test: /\.tsx?$/,
        use: ['babel-loader', 'ts-loader'],
        exclude: /node_modules/,
      },
      {
        test: /\.m?js$/,
        exclude: /(node_modules|bower_components)/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: ['@babel/preset-env']
          }
        }
      }
    ],
  },
  target: ['web', 'es5']
};