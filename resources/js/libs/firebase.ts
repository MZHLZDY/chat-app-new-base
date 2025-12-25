// Import the functions you need from the SDKs you need
import { initializeApp } from "firebase/app";
import { getAnalytics } from "firebase/analytics";
import { getDatabase } from "firebase/database";
import { getAuth, signInAnonymously } from "firebase/auth";
// TODO: Add SDKs for Firebase products that you want to use
// https://firebase.google.com/docs/web/setup#available-libraries

// Your web app's Firebase configuration
// For Firebase JS SDK v7.20.0 and later, measurementId is optional
const firebaseConfig = {
  apiKey: "AIzaSyC3LX1BgAv80_dxgm7rMkXYMJKde7ataSo",
  authDomain: "chat-mti-88.firebaseapp.com",
  databaseURL: "https://chat-mti-88-default-rtdb.asia-southeast1.firebasedatabase.app",
  projectId: "chat-mti-88",
  storageBucket: "chat-mti-88.firebasestorage.app",
  messagingSenderId: "259515011828",
  appId: "1:259515011828:web:2f7ef40add69064d0ac4a1",
  measurementId: "G-0PXW8RZWLG"
};
// Initialize Firebase
const app = initializeApp(firebaseConfig);
const analytics = getAnalytics(app);

const db = getDatabase(app);
const auth = getAuth(app);
signInAnonymously(auth)
  .then(() => {
    console.log('Firebase Auth: Signed in anonymously');
  })
  .catch((error) => {
    console.error('Firebase Auth Error:', error);
  });

export { db, auth };