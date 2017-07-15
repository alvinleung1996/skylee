#include <iostream>
#include <fstream>
#include <experimental/filesystem>
#include <regex>
#include <list>
#include <Magick++.h>

namespace fs = std::experimental::filesystem;
namespace mg = Magick;

struct ImageData {
  std::string filename;
  size_t width;
  size_t height;
};

#define IMAGE_QUALITY 75 // [1, 100] for JPEG

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

//  if (listPath.empty()) {
//    listPath = outDirPath / "images.txt";
//  }
  if (!listPath.empty() && fs::exists(listPath) && !fs::is_regular_file(listPath)) {
    std::cout << "\"" << listPath.string() << "\" is not a regular file" << std::endl;
    return 4;
  }



  std::cout << "Image Builder started." << std::endl;



  for (const fs::directory_entry& entry : fs::directory_iterator(outDirPath)) {
    const fs::path& path = entry.path();
    const std::string& pathStr = path.string();

    if (std::regex_search(pathStr, std::regex(R"(-\d+\.jpg$)"))
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

  std::list<ImageData> imageDataList;

  for (const fs::directory_entry& entry : fs::directory_iterator(inDirPath)) {
    const fs::path& path = entry.path();
    const std::string& pathStr = path.string();
    const fs::path& filenamePath = path.filename();
    const std::string& filenamePathStr = filenamePath.string();

    if (std::regex_search(filenamePathStr, std::regex(R"(.jpg$)")) &&
        fs::is_regular_file(path)) {

      std::cout << "Convert image: \"" << pathStr << "\"" << std::endl;
      mg::Image image;
      try {
        image.read(pathStr);
        size_t imageWidth = image.columns();
        size_t imageHeight = image.rows();

        image.interlaceType(mg::InterlaceType::LineInterlace); // progressive JPEG
        image.quality(IMAGE_QUALITY); // quality [1, 100] for JPEG

        fs::path outputPath = outDirPath / filenamePath;
        std::string outputPathStr = outputPath.string();

        std::cout << "\toutput to: " << outputPathStr << "\"" << std::endl;
        try {
          image.write(outputPathStr);
        } catch (const std::exception& e) {
          std::cout << "Caught Exception: " << e.what() << std::endl;
          exitValue = 200;
        }

        imageDataList.push_back({filenamePathStr, imageWidth, imageHeight});

      } catch (const std::exception& e) {
        std::cout << "Caught Exception: " << e.what() << std::endl;
        exitValue = 100;
      }


    } // end if

  } // end for loop

  if (!listPath.empty()) {
    std::ofstream listOutStream(listPath);

    for (const ImageData& data : imageDataList) {
      listOutStream << " src=\"" << (htmlPrefix + data.filename) << "\""
                    << std::endl;
    }

    for (const ImageData& data : imageDataList) {
      listOutStream << "{ src: `" << (jsPrefix + data.filename) << "` },"
                    << std::endl;
    }
  }

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
