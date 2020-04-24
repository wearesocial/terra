import path from 'path';

export default {
  mode: 'production',
  entry: path.join(__dirname, 'src/terra.js'),
  output: {
    path: path.join(__dirname, 'dist'),
    filename: 'terra.js'
  },
  module: {
    rules: [{
      test: /\.js/,
      exclude: /(node_modules|bower_components)/,
      use: [{
        loader: 'babel-loader'
      }]
    }]
  },
  stats: {
    colors: true
  },
  devtool: 'source-map'
};
