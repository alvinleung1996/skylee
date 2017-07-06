#include <iostream>
#include <fstream>
#include <experimental/filesystem>
#include <regex>
#include <Magick++.h>

namespace fs = std::experimental::filesystem;
namespace mg = Magick;

#define IMAGE_QUALITY 50 // [1, 100] for JPEG

void listArgv(int argc, char** argv);

int main(int argc, char** argv) {
  //listArgv(argc, argv);

  fs::path inDirPath;
  fs::path outDirPath;
  fs::path listPath;
  std::string htmlPrefix;
  std::string jsPrefix;
  bool readOutDirPath = false, readlistPath = false,
       readhtmlPrefix = false, readJsPrefix = false;
  for (int i = 0; i < argc; ++i) {
    if (i == 1) {
      inDirPath = argv[i];

    } else if (std::strcmp(argv[i], "-o") == 0) {
      readOutDirPath = true;
    } else if (readOutDirPath) {
      outDirPath = argv[i]; readOutDirPath = false;

    } else if (std::strcmp(argv[i], "-list") == 0) {
      readlistPath = true;
    } else if (readlistPath) {
      listPath = argv[i]; readlistPath = false;

    } else if (std::strcmp(argv[i], "-html-prefix") == 0) {
      readhtmlPrefix = true;
    } else if (readhtmlPrefix) {
      htmlPrefix = argv[i]; readhtmlPrefix = false;

    } else if (std::strcmp(argv[i], "-js-prefix") == 0) {
      readJsPrefix = true;
    } else if (readJsPrefix) {
      jsPrefix = argv[i]; readJsPrefix = false;
    }
  }

  if (inDirPath.empty() || !fs::is_directory(inDirPath)) {
    std::cout << "\"" << inDirPath.string() << "\" is not a directory" << std::endl;
    return 1;
  }

  if (outDirPath.empty()) {
    outDirPath = inDirPath;
  }
  if (fs::exists(outDirPath) && !fs::is_directory(outDirPath)) {
    std::cout << "\"" << outDirPath.string() << "\" is not a directory" << std::endl;
    return 2;
  }
  if (!fs::exists(outDirPath)) {
    try {
      fs::create_directories(outDirPath);
    } catch (const std::exception& e) {
      std::cout << "Caught exception: " << e.what() << std::endl;
      return 3;
    }
  }

  if (listPath.empty()) {
    listPath = outDirPath / "images.txt";
  }
  if (fs::exists(listPath) && !fs::is_regular_file(listPath)) {
    std::cout << "\"" << listPath.string() << "\" is not a regular file" << std::endl;
    return 4;
  }



  std::cout << "Image Builder started." << std::endl;



  for (const fs::directory_entry& entry : fs::directory_iterator(outDirPath)) {
    const fs::path& path = entry.path();
    const std::string& pathStr = path.string();

    if (std::regex_search(pathStr, std::regex(R"(-\d+(\.\d*)?x.jpg$)"))
      && fs::is_regular_file(path)) {
      std::cout << "Remove \"" << pathStr << "\"" << std::endl;
      try {
        fs::remove(path);
      } catch (const std::exception& e) {
        std::cout << "\tCaught exception: " << e.what() << std::endl << std::endl;
        return 5;
      }
    }
  }



  int exitValue = 0;

  std::ofstream listOutStream(listPath);

  for (const fs::directory_entry& entry : fs::directory_iterator(inDirPath)) {
    const fs::path& path = entry.path();
    const std::string& pathStr = path.string();
    const fs::path& filenamePath = path.filename();
    const std::string& filenamePathStr = filenamePath.string();

    std::smatch filenameMatch;
    if (std::regex_search(filenamePathStr, filenameMatch, std::regex(R"(.jpg$)"))
      && fs::is_regular_file(path)) {
      const std::string& filenameBaseStr = filenameMatch.prefix();

      std::cout << "Convert image: \"" << pathStr << "\"" << std::endl;
      mg::Image oriImage;
      try {
        oriImage.read(pathStr);
        size_t oriWidth = oriImage.columns();
        size_t oriHeight = oriImage.rows();

        float factor = 1;
        size_t modWidth = oriWidth;
        size_t modHeight = oriHeight;

        while (true) {
          mg::Image modImage(oriImage);

          modImage.scale(mg::Geometry(modWidth, modHeight));
          modWidth = modImage.columns();
          modHeight = modImage.rows();

          modImage.interlaceType(mg::InterlaceType::LineInterlace); // progressive JPEG
          modImage.quality(IMAGE_QUALITY); // quality [1, 100] for JPEG

          size_t bufferSize = filenameBaseStr.length()
            + std::to_string(1 / factor).length()
            + 32;
          char* buffer = new char[bufferSize];
          std::snprintf(buffer, bufferSize, "%s-%gx.jpg",
              filenameBaseStr.c_str(), 1/factor);
          std::string outputFilenameStr(buffer);
          std::string outputPathStr = outDirPath.string() + "/" + outputFilenameStr;
          delete[] buffer; buffer = nullptr;

          std::cout << "\toutput to: " << modWidth << "x" << modHeight
              << "@" << (1 / factor) << "x" << " \"" << outputPathStr << "\"" << std::endl;
          try {
            modImage.write(outputPathStr);
          } catch (const std::exception& e) {
            std::cout << "Caught Exception: " << e.what() << std::endl;
            exitValue = 200;
          }

          float nextFactor = factor * 2;
          size_t nextModWidth = static_cast<size_t>(std::round(oriWidth / nextFactor));
          size_t nextModHeight = static_cast<size_t>(std::round(oriHeight / nextFactor));
          if (nextModWidth >= 320 && nextModHeight >= 320) {
            factor = nextFactor;
            modWidth = nextModWidth;
            modHeight = nextModHeight;
          } else {
            break;
          }
        } // end while

        listOutStream << " src=\"" << (htmlPrefix + filenamePathStr) << "\""
                      << " srcWidth=\"" << oriWidth << "\""
                      << " srcHeight=\"" << oriHeight << "\""
                      << std::endl
                      << "src: `" << (jsPrefix + filenamePathStr) << "`"
                      << ", srcWidth: " << oriWidth
                      << ", srcHeight: " << oriHeight
                      << std::endl << std::endl;

      } catch (const std::exception& e) {
        std::cout << "Caught Exception: " << e.what() << std::endl;
        exitValue = 100;
      }

    } // end if

  } // end for loop

  std::cout << "Images list: \"" << listPath.string() << "\"" << std::endl;

  std::cout << "Image Builder finished." << std::endl;

  return exitValue;
}

void listArgv(int argc, char** argv) {
  for (char** v = argv; v != argv + argc; ++v) {
    for (char* c = *v; *c != '\0'; ++c) {
      std::cout << *c;
    }
    std::cout << ' ';
  }
  std::cout << std::endl;
}
